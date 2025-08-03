<?php

namespace Files;

use Application\TaskMaster;
use Library\Json;

class Exif2CreateTask extends TaskMaster
{
    /**
     * Read file's EXIF DateTimeOriginal or CreateDate tags and write to its file system creation date.
     *
     * @param ...$params
     * @return void
     * @throws \JsonException
     */
    public function mainAction(...$params): void
    {
        $this->logger->info('Files Exif2CreateTask mainAction');

        if (count($params) < 1) {
            $this->logger->error('needs file[s]' . PHP_EOL);
            $this->helpAction();
            return;
        }

        $exec = $this->inputParams->print ? 'printf' : 'exec';

        foreach ($params as $param) {
            if (!file_exists($param)) {
                $this->logger->error($param . ' not found' . PHP_EOL);
                return;
            }

            $escFile = escapeshellarg($param);
            $info    = Json::decode(shell_exec('exiftool -j -DateTimeOriginal -CreateDate ' . $escFile));

            if (array_key_exists('DateTimeOriginal', $info[0])) {
                $field = 'DateTimeOriginal';
            } elseif (array_key_exists('CreateDate', $info[0])) {
                $field = 'CreateDate';
            } else {
                $this->logger->warning($param . ' has no DateTimeOriginal or CreateDate' . PHP_EOL);
                continue;
            }

            $date = \DateTime::createFromFormat('Y:m:d H:i:s', $info[0][$field]);

            if($date->format('Y') < 1996) {
                $this->logger->warning($param . ' has invalid DateTimeOriginal or CreateDate' . PHP_EOL);
                continue;
            }

            $exec('setfile -d ' . escapeshellarg($date->format('m/d/Y h:i:s A')) . ' ' . $escFile);
        }
    }

}
