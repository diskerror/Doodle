<?php

namespace AudioMetaData;

use Application\TaskMaster;
use AudioMetaData\DataStruct\RecordingRecord;
use AudioMetaData\DataStruct\RecordingRecordArray;
use AudioMetaData\RecordingProjectsAccess;

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
        $this->logger->info('AudioMetaData CsvTask exportAction');

        if (count($params) != 1) {
            $this->logger->error('needs  output file' . PHP_EOL);
            $this->helpAction();
            return;
        }

        // get all fields of all records
        $records = (new RecordingProjectsAccess())->getWhere('1 ORDER BY main_id ASC');
        if (count($records) == 0) {
            $this->logger->error('AudioMetaData CsvTask exportAction: no records found' . PHP_EOL);
            return;
        }

        $fp      = fopen($params[0], 'wb');
        if ($fp === false) {
            $this->logger->error('AudioMetaData CsvTask exportAction: cannot open/create file ' . $params[0] . PHP_EOL);
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
        $this->logger->info('AudioMetaData CsvTask importAction');

        if (count($params) != 1) {
            $this->logger->error('AudioMetaData CsvTask importAction: missing argument, input file' . PHP_EOL);
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

}
