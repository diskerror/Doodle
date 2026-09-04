<?php

namespace ContextLab;

use Application\TaskMaster;

/**
 * Drive a real multi-turn conversation through Hermes itself and inspect
 * the resulting request-dump JSON. Wraps the same technique as
 * ~/ragger-context-experiments/build_conversations.sh (HERMES_DUMP_REQUESTS=1
 * + `hermes --continue <name> -z "<prompt>"`) but callable as a Doodle
 * task, and parses the dump to surface usage/cache fields directly instead
 * of requiring a separate render step.
 *
 * Prompts are supplied as a JSON array via --prompts-file, one string per
 * turn, sent sequentially against the same continued session so Hermes's
 * own context/compression logic is exercised turn over turn.
 */
class HermesConvoTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => 'prompts-file:', 'desc' => 'JSON array of prompt strings, one per turn', 'isa' => 'String'],
        ['spec' => 'session:', 'desc' => 'Session name for hermes --continue (default contextlab_<timestamp>)', 'isa' => 'String'],
        ['spec' => 'label:', 'desc' => 'Label for saved run directory', 'isa' => 'String'],
        ['spec' => 'save-dir:', 'desc' => 'Where to save turn output (default ContextLab/runs)', 'isa' => 'String'],
        ['spec' => 'timeout:', 'desc' => 'Per-turn timeout seconds (default 180)', 'isa' => 'Number'],
    ];

    public function mainAction(...$args): void
    {
        $promptsFile = $this->getOption('prompts-file');
        if (!$promptsFile || !file_exists($promptsFile)) {
            $this->fail('Provide --prompts-file pointing to a JSON array of prompt strings.');
            return;
        }

        $prompts = json_decode(file_get_contents($promptsFile), true);
        if (!is_array($prompts) || empty($prompts)) {
            $this->fail('prompts-file must contain a non-empty JSON array of strings.');
            return;
        }

        $session = $this->getOption('session', 'contextlab_' . date('YmdHis'));
        $label   = $this->getOption('label', 'hermes-convo');
        $saveDir = $this->getOption('save-dir') ?: (dirname(__DIR__) . '/ContextLab/runs');
        $timeout = (int)$this->getOption('timeout', 180);

        $ts = date('Ymd_His');
        $runDir = "{$saveDir}/{$ts}_{$label}";
        mkdir($runDir, 0755, true);

        $sessDir = getenv('HOME') . '/.hermes/sessions';
        $turns = [];

        foreach ($prompts as $i => $prompt) {
            $turnNum = $i + 1;
            $turnDir = "{$runDir}/turn" . str_pad((string)$turnNum, 2, '0', STR_PAD_LEFT);
            mkdir($turnDir, 0755, true);
            file_put_contents("{$turnDir}/prompt.txt", $prompt);

            // Snapshot existing dump files before the turn.
            $before = glob("{$sessDir}/request_dump_*.json") ?: [];
            $beforeSet = array_flip($before);

            $this->info("[turn {$turnNum}] {$prompt}");

            $cmd = sprintf(
                'HERMES_DUMP_REQUESTS=1 timeout %d hermes --continue %s -z %s',
                $timeout,
                escapeshellarg($session),
                escapeshellarg($prompt)
            );

            $responseOut = [];
            $exitCode = 0;
            exec($cmd . ' 2>' . escapeshellarg("{$turnDir}/stderr.txt"), $responseOut, $exitCode);
            $response = implode("\n", $responseOut);
            file_put_contents("{$turnDir}/response.txt", $response);

            // Find newly created dumps and copy them in.
            $after = glob("{$sessDir}/request_dump_*.json") ?: [];
            $newDumps = array_values(array_diff($after, array_keys($beforeSet)));
            sort($newDumps);

            $usageSummary = null;
            $dumpFiles = [];
            foreach ($newDumps as $dumpPath) {
                $dest = $turnDir . '/' . basename($dumpPath);
                copy($dumpPath, $dest);
                $dumpFiles[] = basename($dumpPath);

                // Try to pull usage out of the dump if it recorded a response.
                $dumpData = json_decode(file_get_contents($dumpPath), true);
                if (isset($dumpData['usage'])) {
                    $usageSummary = $dumpData['usage'];
                } elseif (isset($dumpData['response']['usage'])) {
                    $usageSummary = $dumpData['response']['usage'];
                }
            }

            $turns[] = [
                'turn'         => $turnNum,
                'prompt'       => $prompt,
                'exit_code'    => $exitCode,
                'dump_count'   => count($dumpFiles),
                'dump_files'   => $dumpFiles,
                'usage'        => $usageSummary,
                'response_len' => strlen($response),
            ];
        }

        $summary = [
            'session'  => $session,
            'run_dir'  => $runDir,
            'turns'    => $turns,
        ];

        file_put_contents("{$runDir}/summary.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
