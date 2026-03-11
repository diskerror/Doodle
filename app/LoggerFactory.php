<?php

namespace Application;

/**
 * Simple logger that writes to both a file and STDERR.
 * Replaces the Phalcon-based logger.
 *
 * @copyright     Copyright (c) 2016 Reid Woodbury Jr.
 * @license       http://www.apache.org/licenses/LICENSE-2.0.html	Apache License, Version 2.0
 */
class LoggerFactory
{
    public const OUTPUT_FORMAT = "%s\t%s\t%s";

    private $fileHandle;

    public function __construct(string $fileName)
    {
        $this->fileHandle = fopen($fileName, 'a');
    }

    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }

    /**
     * Log the message.
     * The function name becomes the log level.
     */
    public function __call(string $level, array $params)
    {
        $message = $params[0] ?? '';
        $line    = sprintf(self::OUTPUT_FORMAT, strtoupper($level), date('Y-m-d\TH:i:sP'), $message) . PHP_EOL;

        if ($this->fileHandle) {
            fwrite($this->fileHandle, $line);
        }

        switch ($level) {
            case 'critical':
            case 'emergency':
            case 'error':
            case 'debug':
                fwrite(STDERR, $message . PHP_EOL);
        }
    }
}
