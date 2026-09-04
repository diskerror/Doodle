<?php

namespace ContextLab;

use Application\TaskMaster;
use Library\SQLite3;

/**
 * Pull real turns out of ~/.ragger/memories.db and assemble a synthetic
 * Anthropic messages[] array of a requested shape/size.
 *
 * Uses real conversation history (not lorem ipsum) so the token-length
 * and structural distribution matches what Ragger will actually see.
 * Output is a JSON array of {role, content} ready to embed in a
 * probe request, plus a manifest of which turn_ids were used and their
 * approximate char lengths (for budget bookkeeping).
 */
class BuildFauxContextTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => 'turns:', 'desc' => 'How many turns to pull (default 20)', 'isa' => 'Number'],
        ['spec' => 'db:', 'desc' => 'Path to memories.db (default ~/.ragger/memories.db)', 'isa' => 'String'],
        ['spec' => 'o|output:', 'desc' => 'Output JSON file (default: stdout)', 'isa' => 'String'],
        ['spec' => 'session:', 'desc' => 'Restrict to a specific session_id', 'isa' => 'Number'],
        ['spec' => 'order:', 'desc' => 'oldest|newest|random (default oldest)', 'isa' => 'String'],
        ['spec' => 'min-len:', 'desc' => 'Skip turns shorter than N chars combined (default 0)', 'isa' => 'Number'],
    ];

    public function mainAction(...$args): void
    {
        $n       = (int)$this->getOption('turns', 20);
        $dbPath  = $this->getOption('db') ?: getenv('HOME') . '/.ragger/memories.db';
        $order   = $this->getOption('order', 'oldest');
        $session = $this->getOption('session');
        $minLen  = (int)$this->getOption('min-len', 0);
        $output  = $this->getOption('output');

        if (!file_exists($dbPath)) {
            $this->fail("memories.db not found at: {$dbPath}");
            return;
        }

        $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);

        $orderSql = match ($order) {
            'newest' => 'created_at DESC',
            'random' => 'RANDOM()',
            default  => 'created_at ASC',
        };

        $where = 'user_text IS NOT NULL AND assistant_text IS NOT NULL';
        if ($session !== null) {
            $where .= ' AND session_id = :session_id';
        }
        if ($minLen > 0) {
            $where .= ' AND (LENGTH(user_text) + LENGTH(assistant_text)) >= :min_len';
        }

        $sql = "SELECT turn_id, user_text, assistant_text, session_id, created_at
                FROM turns
                WHERE {$where}
                ORDER BY {$orderSql}
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $n, SQLITE3_INTEGER);
        if ($session !== null) {
            $stmt->bindValue(':session_id', (int)$session, SQLITE3_INTEGER);
        }
        if ($minLen > 0) {
            $stmt->bindValue(':min_len', $minLen, SQLITE3_INTEGER);
        }
        $result = $stmt->execute();

        $messages = [];
        $manifest = [];
        $totalChars = 0;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $userText = trim($row['user_text']);
            $asstText = trim($row['assistant_text']);
            if ($userText === '' || $asstText === '') {
                continue;
            }

            $messages[] = ['role' => 'user', 'content' => $userText];
            $messages[] = ['role' => 'assistant', 'content' => $asstText];

            $len = strlen($userText) + strlen($asstText);
            $totalChars += $len;

            $manifest[] = [
                'turn_id'    => (int)$row['turn_id'],
                'session_id' => (int)$row['session_id'],
                'created_at' => $row['created_at'],
                'chars'      => $len,
            ];
        }

        // If order was 'newest', flip back to chronological so the
        // synthetic conversation reads oldest-to-newest like a real one.
        if ($order === 'newest') {
            $messages = array_reverse($messages);
            // pair-preserve: reverse in pairs, not per-message
            $pairs = array_chunk($messages, 2);
            $pairs = array_reverse($pairs);
            $messages = array_merge(...$pairs);
            $manifest = array_reverse($manifest);
        }

        $result = [
            'source_db'      => $dbPath,
            'turn_count'      => count($manifest),
            'message_count'   => count($messages),
            'total_chars'     => $totalChars,
            'approx_tokens'   => (int)round($totalChars / 4), // rough English heuristic
            'manifest'        => $manifest,
            'messages'        => $messages,
        ];

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($output) {
            file_put_contents($output, $json);
            $this->success("Wrote {$result['message_count']} messages (~{$result['approx_tokens']} tokens) to {$output}");
        } else {
            echo $json . PHP_EOL;
        }
    }
}
