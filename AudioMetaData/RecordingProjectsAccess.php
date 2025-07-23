<?php

namespace AudioMetaData;

use AudioMetaData\DataStruct\RecordingRecord;
use AudioMetaData\DataStruct\RecordingRecordArray;
use SQLite3;

final class RecordingProjectsAccess extends SQLite3
{
    protected const SQLITE_FILE = __DIR__ . '/recording_projects.sqlite';
    protected const TABLE_NAME  = 'main';

    public function __construct()
    {
        parent::__construct(self::SQLITE_FILE);
        parent::enableExceptions(true);
    }

    public function __destruct()
    {
        parent::close();    // Probably not needed
    }

    #[\ReturnTypeWillChange]
    public function query(string $sql): RecordingRecordArray
    {
        $queryResult = parent::query($sql);
        $returnArray = new RecordingRecordArray();
        if ($queryResult !== false) {
            foreach ($queryResult->fetchArray(SQLITE3_ASSOC) as $record) {
                $returnArray[] = $record;
            }
        }

        return $returnArray;
    }

    public function getWhere(string $expression): RecordingRecordArray
    {
        return self::query('SELECT * FROM ' . self::TABLE_NAME . ' WHERE ' . $expression);
    }

    public function getOneWhere(string $expression): ?RecordingRecord
    {
        $response = self::getWhere($expression);

        if ($response->count() === 0) {
            return null;
        }

        return $response[0];
    }

    public function getSessionRecord(string $session): ?RecordingRecord
    {
        $result = self::querySingle(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE session = ' . escapeshellarg($session), true);

        if ($result === false) {
            return null;
        }

        return new RecordingRecord($result);
    }

}
