<?php

namespace Library;

class ProcessRunner
{
    const int MAX_PROCS = 4;

    public readonly int       $maxProcs;
    private readonly iterable $commands;
    private readonly ?string  $cwd;
    private array             $processes = [];


    /**
     * ProcessRunner constructor.
     *
     * @param iterable    $commands
     * @param string|null $cwd
     */
    public function __construct(iterable $commands, ?string $cwd = null)
    {
        // Get the maximum number of level-zero processors, or performance processors available
        $maxProcs       = (int)('0' . exec('sysctl -n hw.perflevel0.physicalcpu 2>/dev/null'));
        $this->maxProcs = $maxProcs === 0 ? self::MAX_PROCS : $maxProcs;

        if ($cwd !== null && !is_dir($cwd)) {
            throw new \InvalidArgumentException('The path "' . $cwd . '" is not a directory.');
        }
        $this->cwd = $cwd;

        $this->commands = $commands;
    }

    public function run(): void
    {
        $numCommands = count($this->commands);

        // Start the maximum number of processes
        for ($pNum = 0, $cmdNum = 0; $pNum < $this->maxProcs && $cmdNum < $numCommands; $pNum++, $cmdNum++) {
            $this->processes[$pNum] = new CmdBuffer($this->commands[$cmdNum], $this->cwd);
//            echo "\r", $this->runningString();
        }

        // Start remaining processes as earlier processes finish
        while ($cmdNum < $numCommands) {
            usleep(100000);
            for ($pNum = 0; $pNum < $this->maxProcs && $cmdNum < $numCommands; $pNum++) {
                if (!$this->processes[$pNum]->isRunning()) {
                    $this->processes[$pNum] = new CmdBuffer($this->commands[$cmdNum]);
                    $cmdNum++;
                }
            }
//            echo "\r", $this->runningString();
        }

    }

    public function wait(): void
    {
        // Wait for all processes to finish
        do {
            usleep(100000);
            $running = false;
            foreach ($this->processes as $process) {
                if ($process->isRunning()) {
                    $running = true;
                    break;
                }
            }
//            echo "\r", $this->runningString();
        } while ($running);
    }

    protected function runningString(): string
    {
        $str = '';
        foreach ($this->processes as $pNum => $process) {
            $str .= $process->isRunning() ? '1' : '0';
        }
        return $str;
    }

    public function __destruct()
    {
        foreach ($this->processes as $process) {
            if (isset($process)) {
                unset($process);
            }
        }
    }

}
