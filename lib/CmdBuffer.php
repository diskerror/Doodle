<?php
/** @noinspection SpellCheckingInspection */

namespace Library;

/**
 * Class CurlBuffer
 *
 * @package Service
 *
 */
class CmdBuffer
{
    private const MAX_READ = 65536;
    private readonly array $pipes;
    private readonly mixed $procRes;

    public function __construct(string $cmd, ?string $cwd = null)
    {
        if ($cwd !== null && !is_dir($cwd)) {
            throw new \InvalidArgumentException('The path "' . $cwd . '" is not a directory.');
        }

        //		StdIo::outln($cmd);die;
        //  Errors are sent immediately to STDERR
        $pipes         = [];
        $this->procRes = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], STDERR], $pipes, $cwd);
        $this->pipes   = $pipes;
    }

    public function __destruct()
    {
        if (isset($this->procRes)) {
            if ($this->isRunning()) {
                proc_terminate($this->procRes);
            }

            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($this->procRes);
        }
    }

    public function isEOF(): bool
    {
        return feof($this->pipes[1]);
    }

    public function read($length = self::MAX_READ): string
    {
        return fread($this->pipes[1], $length);
    }

    public function readLn($length = self::MAX_READ): string
    {
        return fgets($this->pipes[1], $length);
    }

    public function write($data): bool
    {
        return fwrite($this->pipes[0], $data);
    }

    public function getStatus(): array
    {
        return proc_get_status($this->procRes);
    }

    public function getPID(): int
    {
        return $this->getStatus()['pid'];
    }

    public function isRunning(): bool
    {
        return $this->getStatus()['running'];
    }

    public function wasSignaled(): bool
    {
        return $this->getStatus()['signaled'];
    }

    public function wasStopped(): bool
    {
        return $this->getStatus()['stopped'];
    }

    public function exitCode(): int
    {
        return $this->getStatus()['exitcode'];
    }
}
