<?php

namespace Music;

use Application\Command;
use ErrorException;
use Library\Exception\RuntimeException;
use Library\StdIo;
use SQLite3;


//function esc($v)
//{
////	return str_replace([' ', "'", '"', '(', ')'], ['\\ ', "\\'", '\\"', '\\(', '\\)'], $v);
////	return str_replace(["'"], ["\\\\'"], $v);
//	return escapeshellarg($v);
//}


class Apply extends Command
{
    /**
     * Applies forScore meta-data to PDF file by file name.
     *
     * @return int
     * @throws ErrorException
     */
    public function main(): int
    {
        if (count($this->inputParams->arguments) === 0) {
            $this->help();
            return 0;
        }

        $db = new SQLite3(__DIR__ . '/music.sqlite');
        $db->enableExceptions(true);

        foreach ($this->inputParams->arguments as $argument) {
            if (!is_file($argument->arg)) {
                throw new RuntimeException('Not a file.' . PHP_EOL . '  ' . $argument->arg);
            }

            $output      = '';
            $result_code = 0;
            $bname       = basename($argument->arg);

            $m = new PdfMetaData(
                $db->querySingle("SELECT title, author, subject, keywords FROM meta WHERE filename = '$bname'", true)
            );

            if ($m->title !== '') {
                foreach ($m as &$v) {
                    if ($v !== null) {
                        $v = escapeshellarg($v);
                    }
                }
                $arg = escapeshellarg($arg);

                $cmd = 'exiftool -overwrite_original ' .
                    "-Title=$m->title -Author=$m->author -Subject=$m->subject -Keywords=$m->keywords $arg";

//        StdIo::outln($cmd);
//        continue;
                exec($cmd, $output, $result_code);

                if ($result_code) {
                    throw new RuntimeException('ERROR: ' . $cmd . PHP_EOL . $output . PHP_EOL . $result_code);
                }
                else {
                    StdIo::outln($bname . ' - done');
                }
            }
            else {
                throw new RuntimeException('File has no meta data.' . PHP_EOL . '  ' . $argument->arg);
            }
        }

        return 0;
    }

}
