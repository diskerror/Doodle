<?php

namespace Music;

use Application\TaskMaster;
use ErrorException;
use Library\Exception\RuntimeException;
use Library\StdIo;
use SQLite3;


class ApplyMetaTask extends TaskMaster
{
    /**
     * Applies forScore meta-data to PDF file by file name.
     *
     * @return int
     * @throws ErrorException
     */
    public function mainAction()
    {
        $files = $this->inputParams->arguments;
        array_shift($files);

        if (count($files) === 0) {
            $this->helpAction();
            return;
        }

        $db = new SQLite3(__DIR__ . '/music.sqlite');
        $db->enableExceptions(true);

        foreach ($files as $fileArg) {
            $file = $fileArg->arg;

            if (!is_file($file)) {
                throw new RuntimeException('Not a file.' . PHP_EOL . '  ' . $file);
            }

            $output      = '';
            $result_code = 0;
            $bname       = basename($file);

            $m = new PdfMetaData(
                $db->querySingle("SELECT title, author, subject, keywords FROM meta WHERE filename = '$bname'", true)
            );

            if ($m->title !== '') {
                foreach ($m as &$v) {
                    if ($v !== null) {
                        $v = escapeshellarg($v);
                    }
                }
                $file = escapeshellarg($file);

                $cmd = 'exiftool -overwrite_original ' .
                    "-Title=$m->title -Author=$m->author -Subject=$m->subject -Keywords=$m->keywords $file";

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
                throw new RuntimeException('File has no meta data.' . PHP_EOL . '  ' . $file);
            }
        }
    }

}
