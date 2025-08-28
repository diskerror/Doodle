<?php

namespace Music;

use Application\TaskMaster;
use ErrorException;
use Library\Exceptions\BadFileException;
use Library\Exceptions\RuntimeException;
use Library\StdIo;
use SQLite3;


class DataTask extends TaskMaster
{
    /**
     * Applies forScore meta-data to PDF file by file name.
     *
     * @return int
     * @throws ErrorException
     */
    public function applyAction(...$files)
    {
        if (count($files) === 0) {
            $this->helpAction();
            return;
        }

        $db = new SQLite3(__DIR__ . '/music.sqlite');
        $db->enableExceptions(true);

        foreach ($files as $file) {

            if (!is_file($file)) {
                throw new BadFileException('Not a file.' . PHP_EOL . '  ' . $file . PHP_EOL);
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
                StdIo::outln('File has no meta data.');
                StdIo::outln('  ' . $file);
            }
        }
    }

    public function setDbTask()
    {
//    disabled    $db = new SQLite3(__DIR__ . '/music.sqlite');
        $db->exec('DROP TABLE if EXISTS meta');
        $db->exec('
CREATE TABLE meta (
    meta_id INTEGER PRIMARY KEY,
    filename text,
    title text,
    author text,
    subject text,
    keywords text,
    rating INTEGER,
    difficulty INTEGER,
    duration INTEGER,
    keysf INTEGER,
    keymi INTEGER)
');
        $db->exec('CREATE UNIQUE INDEX idx_filename ON meta (filename)');
        $db->exec('CREATE INDEX idx_title ON meta (title)');
        $db->exec('CREATE INDEX idx_author ON meta (author)');
        $db->exec('CREATE INDEX idx_subject ON meta (subject)');
        $db->exec('CREATE INDEX idx_keywords ON meta (keywords)');

    }

    public function exportAction(...$params)
    {
        $db  = new SQLite3(__DIR__ . '/music.sqlite');
        $res = $db->query('SELECT * FROM meta ORDER BY meta_id');

        $row = $res->fetchArray(SQLITE3_ASSOC);

        $fp = fopen($params[0], 'wb');
        fputcsv($fp, array_keys($row));
        fputcsv($fp, $row);

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            fputcsv($fp, $row);
        }

        $db->close();
        fclose($fp);
    }

    public function fixAction()
    {
        ini_set('memory_limit', -1);

        $db  = new SQLite3(__DIR__ . '/music.sqlite');
        $res = $db->query('SELECT * FROM meta WHERE keywords != "" ORDER BY meta_id DESC');

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $db->exec('UPDATE meta SET keywords = "' . preg_replace('/,\s*/', ', ',
                                                                    $row['keywords']) . '" WHERE filename = "' . $row['filename'] . '"');
        }
        $db->close();
    }

}
