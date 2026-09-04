<?php

namespace ContextLab;

use Application\TaskMaster;

/**
 * Send a directly-constructed request straight to the Anthropic Messages
 * API (no Hermes in the loop). Full control over cache_control placement
 * on system + message blocks. Reports the complete `usage` block so you
 * can see cache_read_input_tokens / cache_creation_input_tokens directly.
 *
 * Input: a JSON file (from BuildFauxContextTask, or hand-rolled) shaped as:
 *   { "system": "...", "messages": [ {"role":"user","content":"..."}, ... ] }
 * Optional --cache-at N,M inserts cache_control ephemeral breakpoints
 * after the Nth and Mth message (0-indexed) plus always on the system
 * prompt block. --swap-tail-file lets you splice in a replacement digest
 * for testing "cold turn replacement" cache survival directly.
 */
class ProbeTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => 'i|input:', 'desc' => 'Input JSON file with system+messages', 'isa' => 'String'],
        ['spec' => 'prompt:', 'desc' => 'Single ad-hoc user prompt (skips --input)', 'isa' => 'String'],
        ['spec' => 'model:', 'desc' => 'Model id (default claude-sonnet-5)', 'isa' => 'String'],
        ['spec' => 'key:', 'desc' => 'API key override (default: read ANTHROPIC_API_KEY from ~/.hermes/.env)', 'isa' => 'String'],
        ['spec' => 'max-tokens:', 'desc' => 'max_tokens for the response (default 1024)', 'isa' => 'Number'],
        ['spec' => 'beta:', 'desc' => 'anthropic-beta header value, e.g. context-1m-2025-08-07', 'isa' => 'String'],
        ['spec' => 'cache-at:', 'desc' => 'Comma-separated message indices to place cache_control breakpoints after', 'isa' => 'String'],
        ['spec' => 'system-cache', 'desc' => 'Also mark the system prompt block as ephemeral cache'],
        ['spec' => 'label:', 'desc' => 'Label for saved run directory', 'isa' => 'String'],
        ['spec' => 'save-dir:', 'desc' => 'Where to save request/response pairs (default ContextLab/runs)', 'isa' => 'String'],
    ];

    public function mainAction(...$args): void
    {
        $model     = $this->getOption('model', 'claude-sonnet-5');
        $maxTokens = (int)$this->getOption('max-tokens', 1024);
        $beta      = $this->getOption('beta');
        $cacheAt   = $this->getOption('cache-at');
        $systemCache = (bool)$this->getOption('system-cache', false);
        $label     = $this->getOption('label', 'probe');
        $saveDir   = $this->getOption('save-dir') ?: (dirname(__DIR__) . '/ContextLab/runs');

        $key = $this->getOption('key') ?: $this->readAnthropicKey();
        if (!$key) {
            $this->fail('No Anthropic API key found. Pass --key or set ANTHROPIC_API_KEY in ~/.hermes/.env');
            return;
        }

        $system = 'You are a helpful assistant in a controlled context-caching experiment.';
        $messages = [];

        $inputFile = $this->getOption('input');
        $adhocPrompt = $this->getOption('prompt');

        if ($inputFile) {
            if (!file_exists($inputFile)) {
                $this->fail("Input file not found: {$inputFile}");
                return;
            }
            $data = json_decode(file_get_contents($inputFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->fail('Invalid JSON in input file: ' . json_last_error_msg());
                return;
            }
            $system = $data['system'] ?? $system;
            $messages = $data['messages'] ?? [];
        }

        if ($adhocPrompt) {
            $messages[] = ['role' => 'user', 'content' => $adhocPrompt];
        }

        if (empty($messages)) {
            $this->fail('No messages to send. Use --input or --prompt.');
            return;
        }

        // Normalize content into Anthropic block form and apply cache_control.
        $cacheIndices = [];
        if ($cacheAt !== null && $cacheAt !== '') {
            $cacheIndices = array_map('intval', explode(',', $cacheAt));
        }

        $anthropicMessages = [];
        foreach ($messages as $idx => $msg) {
            $content = $msg['content'];
            $block = ['type' => 'text', 'text' => is_string($content) ? $content : json_encode($content)];
            if (in_array($idx, $cacheIndices, true)) {
                $block['cache_control'] = ['type' => 'ephemeral'];
            }
            $anthropicMessages[] = [
                'role' => $msg['role'],
                'content' => [$block],
            ];
        }

        $systemBlocks = [
            ['type' => 'text', 'text' => $system],
        ];
        if ($systemCache) {
            $systemBlocks[0]['cache_control'] = ['type' => 'ephemeral'];
        }

        $requestBody = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemBlocks,
            'messages'   => $anthropicMessages,
        ];

        $headers = [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ];
        if ($beta) {
            $headers[] = 'anthropic-beta: ' . $beta;
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($requestBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $start = microtime(true);
        $responseRaw = curl_exec($ch);
        $elapsed = microtime(true) - $start;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($responseRaw === false) {
            $this->fail("curl error: {$curlErr}");
            return;
        }

        $response = json_decode($responseRaw, true);

        // Save the pair for later comparison.
        $ts = date('Ymd_His');
        $runDir = "{$saveDir}/{$ts}_{$label}";
        if (!is_dir($runDir)) {
            mkdir($runDir, 0755, true);
        }
        file_put_contents("{$runDir}/request.json", json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents("{$runDir}/response.json", $responseRaw);

        $usage = $response['usage'] ?? null;

        $summary = [
            'http_code'      => $httpCode,
            'elapsed_sec'    => round($elapsed, 3),
            'model'          => $model,
            'message_count'  => count($anthropicMessages),
            'cache_indices'  => $cacheIndices,
            'system_cached'  => $systemCache,
            'usage'          => $usage,
            'run_dir'        => $runDir,
        ];

        if ($httpCode !== 200) {
            $summary['error'] = $response['error'] ?? $response;
        }

        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    /**
     * Read ANTHROPIC_API_KEY out of ~/.hermes/.env without pulling in a
     * dotenv library — it's a flat KEY=VALUE file.
     */
    private function readAnthropicKey(): ?string
    {
        $envPath = getenv('HOME') . '/.hermes/.env';
        if (!file_exists($envPath)) {
            return null;
        }
        foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^ANTHROPIC_API_KEY\s*=\s*(.+)$/', trim($line), $m)) {
                return trim($m[1], "\"' \t");
            }
        }
        return null;
    }
}
