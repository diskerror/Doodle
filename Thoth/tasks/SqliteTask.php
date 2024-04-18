<?php

use Service\StdIo;

class SqliteTask extends TaskMaster
{
	/**
	 * Dumps all data in SQLite file to screen.
	 *
	 * @param string $fName
	 *
	 * @return void
	 */
	function dumpAction(string $fName)
	{
		if (!file_exists($fName)) {
			throw new RuntimeException('SQLite file does not exist');
		}

		$db = new Sqlite3($fName, SQLITE3_OPEN_READONLY);
		$db->exec('PRAGMA journal_mode = wal;');
		StdIo::outln('SQLite version: ' . $db->version()['versionString']);

		$tables = $db->query('PRAGMA table_list');
		if ($tables === false) {
			StdIo::outln('PRAGMA table_list returned false');
			return;
		}

		while ($table = $tables->fetchArray(SQLITE3_ASSOC)) {
			$tDump = $db->query('SELECT * FROM ' . $table['name']);
			if ($tDump === false) {
				StdIo::outln($table['name'] . ' query returned false');
				StdIo::outln();
				continue;
			}

			StdIo::outln($table['name'] . ':');
			while ($td = $tDump->fetchArray(SQLITE3_ASSOC)) {
				StdIo::jsonOut($td);
			}
			StdIo::outln();
		}
	}
}
