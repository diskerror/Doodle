<?php

namespace Recordings;

use Application\TaskMaster;
use Recordings\DataStruct\RecordingRecord;
use Recordings\DataStruct\RecordingRecordArray;
use Recordings\RecordingProjectsAccess;

class CsvTask extends TaskMaster
{
    /**
     * Export SQLite database to CSV file
     *
     * @param ...$params
     * @return void
     */
    public function exportAction(...$params)
    {
        $this->logger->info('Recordings CsvTask exportAction');

        if (count($params) != 1) {
            $this->logger->error('needs  output file' . PHP_EOL);
            $this->helpAction();
            return;
        }

        // get all fields of all records
        $records = (new RecordingProjectsAccess())->getWhere('1 ORDER BY main_id ASC');
        if (count($records) == 0) {
            $this->logger->error('Recordings CsvTask exportAction: no records found' . PHP_EOL);
            return;
        }

        $fp      = fopen($params[0], 'wb');
        if ($fp === false) {
            $this->logger->error('Recordings CsvTask exportAction: cannot open/create file ' . $params[0] . PHP_EOL);
            return;
        }

        fputcsv($fp, $records[0]->getPublicNames());    // header
        foreach ($records as $record) {
            fputcsv($fp, $record->toArray());           // each record
        }
        fclose($fp);

    }

    /**
     * Import CSV file to SQLite database
     * CSV fields that do not exist in the database will be ignored
     *
     * @param ...$params
     * @return void
     */
    public function importAction(...$params)
    {
        $this->logger->info('Recordings CsvTask importAction');

        if (count($params) != 1) {
            $this->logger->error('Recordings CsvTask importAction: missing argument, input file' . PHP_EOL);
            $this->helpAction();
            return;
        }

        $recordNames = (new RecordingRecord())->getPublicNames();

        $statementStr = 'INSERT OR REPLACE INTO main (';
        $statementStr .= implode(', ', $recordNames);
        $statementStr .= ")\nVALUES (:";
        $statementStr .= implode(', :', $recordNames);
        $statementStr .= ')';

        $access = new RecordingProjectsAccess();
        $statement = (new RecordingProjectsAccess())->prepare($statementStr);

        $fp      = fopen($params[0], 'rb');
        $header  = fgetcsv($fp);
        while ($record = fgetcsv($fp)) {
            $record = new RecordingRecord(array_combine($header, $record));
            foreach ($record as $name => $value) {
                $statement->bindValue(':' . $name, $value);
            }
            $statement->execute();
        }
        fclose($fp);
    }

    /**
     * Create recording projects database
     */
    public function createDbAction()
    {
        $this->logger->info('Recordings LoadTask createDbAction');

        $recordNames = (new RecordingRecord())->getPublicNames();
        $sqlString   = "CREATE TABLE IF NOT EXISTS main (\n    main_id  INTEGER PRIMARY KEY,\n    ";
        $sqlString   .= implode("  TEXT DEFAULT '',\n    ", $recordNames);
        $sqlString   .= "  TEXT DEFAULT ''\n)\n";

        $db = new RecordingProjectsAccess();
        $db->exec('DROP TABLE if EXISTS main');
        $db->exec($sqlString);

        $db->exec('CREATE INDEX idx_tape_date ON main (tape, recorded_on)');
        $db->exec('CREATE UNIQUE INDEX idx_session ON main (session)');
        $db->exec('CREATE INDEX idx_recorded_on ON main (recorded_on)');
        $db->exec('CREATE INDEX idx_loaded_on ON main (loaded_on)');
        $db->exec('CREATE INDEX idx_edited_on ON main (edited_on)');
        $db->exec('CREATE INDEX idx_uploaded_on ON main (uploaded_on)');
        $db->exec('CREATE INDEX idx_title ON main (title)');
    }

}
