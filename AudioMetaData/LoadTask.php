<?php

namespace AudioMetaData;

use Application\TaskMaster;
use AudioMetaData\DataStruct\RecordingRecord;
use SQLite3;

/**
 * Class LoadTask
 *
 * Create and load SQLite file for tracking the status and meta data of my concert recordings.
 *
 */
class LoadTask extends TaskMaster
{
//    const SQLITE_FILE = __DIR__ . '/recording_projects.sqlite';


    /**
     * Create recording projects database
     */
    public function createDbAction()
    {
        $this->logger->info('AudioMetaData LoadTask createDbAction');

//        $db = new SQLite3(self::SQLITE_FILE);
//        $db->exec('DROP TABLE if EXISTS main');
//        $db->exec('
//CREATE TABLE main (
//    main_id      INTEGER PRIMARY KEY,
//    tape         text DEFAULT "", -- name of tape
//    location     text DEFAULT "", -- location recording was made
//    recorded_on  text DEFAULT "", -- date and time tape was recorded
//    reference    text DEFAULT "", -- machine code
//    medium       text DEFAULT "", -- tape medium type
//    encoding     text DEFAULT "", -- tape encoding type
//    loaded_on    text DEFAULT "", -- date tape was loaded into session
//    session      text DEFAULT "", -- session name
//    notes        text DEFAULT "",
//    edited_on    text DEFAULT "", -- date session was edited
//    uploaded_on  text DEFAULT "", -- upload date
//    description  text DEFAULT ""
//	performers   text DEFAULT ""
//)');
//        $db->exec('CREATE INDEX idx_tape_date ON main (tape, recorded_on)');
//        $db->exec('CREATE UNIQUE INDEX idx_session ON main (session)');
//        $db->exec('CREATE INDEX idx_recorded_on ON main (recorded_on)');
//        $db->exec('CREATE INDEX idx_loaded_on ON main (loaded_on)');
//        $db->exec('CREATE INDEX idx_upload_on ON main (uploaded_on)');
//
//        $db->close();
    }


    /**
     * Load data from CSV version of original working EXCEL spreadsheet
     *
     * @return void
     */
    public function csvAction(...$params)
    {
        $this->logger->info('AudioMetaData LoadTask csvAction');

        if (count($params) != 1) {
            $this->logger->error('AudioMetaData LoadTask csvAction: missing argument, need csv file' . PHP_EOL);
            $this->helpAction();
            return;
        }

        $file = fopen($params[0], 'r');
        if ($file === false) {
            $this->logger->error('AudioMetaData LoadTask csvAction: ' . $params[0] . ' not found');
            return;
        }

        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        $db = new SQLite3(self::SQLITE_FILE);

        fgetcsv($file); // skip header
        while (($row = fgetcsv($file)) !== false) {
//  0 tape
//  1 recorded on
//  2 time
//  3 medium
//  4 encoding
//  5 pre_emph
//  6 loaded
//  7 session
//  8 uploaded
//  9 Notes
//  10 Description
//  11 machine code
            $r = new RecordingRecord(
                [
                    'tape' => $row[0],
                    'recorded_on' => $row[1] === '' ? 0 : $row[1] . ' ' . $row[2],
                    'machine' => $row[11],
                    'medium' => $row[3],
                    'encoding' => $row[4],
                    'pre_emph' => $row[5] === 'EIAJ' ? true : false,
                    'loaded_on' => 0,
                    'session' => strlen($row[7]) < 5 ? ($row[0] . ' ' . $row[1]) : $row[7],
                    'edited_on' => $row[8] === '' ? 0 : $row[8],
                    'uploaded_on' => $row[8] === '' ? 0 : $row[8],
                    'notes' => $row[9],
                    'description' => $row[10],
                ]);

            $db->exec('
INSERT INTO main (
    tape,
    recorded_on,
    machine,
    medium,
    encoding,
    pre_emph,
    loaded_on,
    session,
    notes,
    edited_on,
    uploaded_on,
    description
) VALUES (
    "' . $r->tape . '",
    "' . $r->recorded_on . '",
    "' . $r->machine . '",
    "' . $r->medium . '",
    "' . $r->encoding . '",
    ' . (int)$r->pre_emph . ',
    "' . $r->loaded_on . '",
    "' . $r->session . '",
    "' . $r->notes . '",
    "' . $r->edited_on . '",
    "' . $r->uploaded_on . '",
    "' . $r->description . '"
)');
        }

        fclose($file);
        $db->close();
    }

    /**
     * Get mod dates from old ProTools LE 8.0 sessions (.ptf') before they were saved
     * in the current format ProTools 25.6.
     *
     * @return void
     */
    public function getLoadDatesAction(...$params)
    {
        $this->logger->info('AudioMetaData LoadTask getLoadDatesAction');

        if (count($params) != 1) {
            $this->logger->error('AudioMetaData LoadTask getLoadDatesAction: missing argument, need directory' . PHP_EOL);
            $this->helpAction();
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($params[0]));
        if ($files === false) {
            $this->logger->error('AudioMetaData LoadTask getLoadDatesAction: ' . $params[0] . ' not found');
            return;
        }

        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        $db = new SQLite3(self::SQLITE_FILE);

        foreach ($files as $name => $file) {
            if (
                $file->isFile() &&
                $file->getExtension() === 'ptf' &&
                preg_match('/.*Session File Backups.*/', $name) !== 1
            ) {
                try {
                    $db->exec('
                        UPDATE main
                        SET loaded_on = "' . date('Y-m-d', $file->getMtime()) . '"
                        WHERE session = "' . $file->getBasename('.ptf') . '"
                    ');
                }
                catch (Exception $e) {
                    echo $file->getBasename('.ptf') . ' - ' . date('Y-m-d', $file->getMtime()) . PHP_EOL;
                }
            }
        }
    }

    /**
     * sample input:
     * bexttool -Originator 'Reid Woodbury Jr.' -TimeReference 0 -Version 0 \
     *   -OriginatorReference 'USRAWPCMF180118019910511220000'
     *   -OriginationDate '1991-05-11' -OriginationTime '22:00:00' -Description \
     *   'Caltech-Occindental Wind Ensemble and Jazz Bands recorded in Beckman Auditorium, Pasadena CA.' \
     *   Caltech\ Bands\ 1991-05-11.wav
     */
    public function bparseAction(...$params)
    {
        $this->logger->info('AudioMetaData LoadTask bparseAction');

        $file = fopen($params[0], 'r');
        if ($file === false) {
            $this->logger->error('AudioMetaData LoadTask bparseAction: ' . $params[0] . ' not found');
            return;
        }

        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        $db = new SQLite3(self::SQLITE_FILE);
        while (($line = fgets($file)) !== false) {
            //  Leverage shell's ability to parse arguments
            $packet = json_decode(exec('php -r "echo json_encode(\\$argv);" -- ' . $line));

            $data = [];
            //  We know what the first two elements are and can skip them
            //  We also know that the last element is the filename
            $pct = count($packet) - 2;
            for ($p = 2; $p < $pct; $p++) {
                if (substr($packet[$p], 0, 1) === '-') {
                    $data[substr($packet[$p], 1)] = $packet[$p + 1];
                }
            }
            $session = basename(array_pop($packet), '.wav');

            if (array_key_exists('Description', $data)) {
                $db->exec('
                UPDATE main
                SET description = "' . $data['Description'] . '"
                WHERE session = "' . $session . '"
            ');
            }
        }

        fclose($file);
        $db->close();
    }
}
