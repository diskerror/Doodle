<?php

namespace Forensics;

use Application\TaskMaster;
use Library\Exceptions\BadFileException;
use Library\StdIo;

class DumpSQLiteTask extends TaskMaster
{
	/**
	 * Dump contents of SQLite file as JSON.
	 *
	 * @return int
	 * @throws ErrorException
	 */
	public function mainAction(): int
	{
		if (count($this->inputParams->arguments) === 0) {
			StdIo::outln('Requires path to SQLite file.');
			$this->help();
			return 0;
		}

		if (!is_file($this->inputParams->arguments[0]->arg)) {
			throw new BadFileException();
		}

		$db = new PDO('db:' . $this->inputParams->arguments[0]->arg, SQLITE3_OPEN_READONLY);

		$tstm = $db->query('SELECT name FROM sqlite_schema');
		$tstm->execute();
		$tables = $tstm->fetchAll(PDO::FETCH_COLUMN);

		$output = [];

		foreach ($tables as $table) {
			try {
				$tstm = $db->query('SELECT * FROM ' . $table);
				$tstm->execute();
				$output[$table] = $tstm->fetchAll(PDO::FETCH_ASSOC);
			}
			catch (Exception $e) {
			}
		}

		echo json_encode($output, JSON_PRETTY_PRINT);

		return 0;
	}
}
