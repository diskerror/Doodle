<?php

namespace Music;

use Application\TaskMaster;
use ErrorException;
use Library\Exceptions\BadFileException;
use Library\Exceptions\RuntimeException;
use Library\StdIo;
use Library\SQLite3;
use Shuchkin\SimpleXLSX;
use SplFileObject;


class MetaDataTask extends TaskMaster
{
    /**
     * mainAction
     *
     * Applies meta-data to PDF file by file name.
     *
     * @return void
     * @throws ErrorException
     */
    public function mainAction(...$files): void
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

                $cmd = 'exiftool -overwrite_original -Creator="Reid Woodbury Jr." ' .
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

    /**
     * setDbAction
     *
     * Initializes the database.
     * WARNING: This will delete the and existing database!
     *
     * @return void
     */
    public function setDbAction()
    {
        StdIo::outln('WARNING: This will delete the and existing database!');
        StdIo::out('Continue? (y/n)');
        $response = StdIo::in();
        if (trim($response) !== 'y') {
            return;
        }

        $db = new SQLite3(__DIR__ . '/music.sqlite');
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

    /**
     * exportAction
     *
     * Exports the database to a CSV file.
     * If the file name ends in .tsv, the separator will be a tab.
     * Otherwise, the separator will be a comma.
     *
     * @param ...$args
     * @return void
     */
    public function exportAction(...$args)
    {
        if (count($args) !== 1) {
            StdIo::outln('needs output file name');
            $this->helpAction();
            return;
        }

        $fo        = new SplFileObject($args[0], 'wb');
        $separator = ',';
        if (strtolower($fo->getExtension()) === 'tsv') {
            $separator = "\t";
        }

        $db  = new SQLite3(__DIR__ . '/music.sqlite');
        $res = $db->query('SELECT * FROM meta ORDER BY meta_id');

        $row = $res->fetchArray(SQLITE3_ASSOC);
        $fo->fputcsv(array_keys($row), $separator);
        $fo->fputcsv($row, $separator);

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $fo->fputcsv($row, $separator);
        }
    }

    /**
     * fixAction
     * Fixes the database by removing extra spaces from the keywords, reporting fields that are too long, etc.
     * @return void
     */
    public function fixAction()
    {
        ini_set('memory_limit', -1);

        $db  = new SQLite3(__DIR__ . '/music.sqlite');
        $res = $db->query('SELECT * FROM meta WHERE keywords != "" ORDER BY meta_id DESC');

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $db->exec(
                'UPDATE meta SET keywords = "' .
                preg_replace('/,\s*/', ', ', $row['keywords']) .
                '" WHERE filename = "' . $row['filename'] . '"'
            );
        }
        $db->close();
    }
    }

}
