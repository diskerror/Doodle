<?php

namespace AudioMetaData;

use AudioMetaData\DataStruct\RecordingRecord;
use AudioMetaData\DataStruct\RecordingRecordArray;
use SQLite3;

class RecordingProjectsAccess
{
    protected const SQLITE_DB  = 'recording_projects.db';
    protected const TABLE_NAME = 'main';

    protected static SQLite3 $db;
    protected static int     $dbAccessors = 0;

    public function __construct()
    {
        if (!isset(self::$db)) {
            self::$db = new SQLite3(SQLITE_DB);
        }

        self::$dbAccessors++;
    }

    public function __destruct()
    {
        self::$dbAccessors--;
        if (self::$dbAccessors > 0) {
            return;
        }
        self::$db->close();
    }

    public function exec(string $sql): bool
    {
        return self::$db->exec($sql);
    }

    public function query(string $sql): RecordingRecordArray
    {
        $queryResult = self::$db->query($sql);
        $returnArray = new RecordingRecordArray();
        while ($row = $queryResult->fetchArray(SQLITE3_ASSOC)) {
            $returnArray[] = $row;
        }

        return $returnArray;
    }

    public function getWhere(string $expression): RecordingRecordArray
    {
        return $this->query('SELECT * FROM ' . self::TABLE_NAME . ' WHERE ' . $expression);
    }

    public function getOneWhere(string $expression): RecordingRecord
    {
        return $this->getWhere($expression)[0];
    }

}
