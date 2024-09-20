<?php

use Application\Command;
use Application\Exceptions\BadFileException;
use Library\StdIo;


class dumpSQLite extends Command
{
	/**
	 * Dump contents of SQLite file as JSON.
	 *
	 * @return int
	 * @throws ErrorException
	 */
	public function main(): int
	{
		if (count($this->inputParams->arguments) === 0) {
			StdIo::outln('Requires path to SQLite file.');
			$this->help();
			return 0;
		}

		if (!is_file($this->inputParams->arguments[0]->arg)) {
			throw new BadFileException();
		}

		$db = new PDO('sqlite:' . $this->inputParams->arguments[0]->arg, SQLITE3_OPEN_READONLY);

		$tstm = $db->query('SELECT name FROM sqlite_schema');
		$tstm->execute();
		$tables = $tstm->fetchAll(PDO::FETCH_COLUMN);

		$data = [];

		foreach ($tables as $table) {
			try {
				$tstm = $db->query('SELECT * FROM ' . $table);
				$tstm->execute();
				$data[$table] = $tstm->fetchAll(PDO::FETCH_ASSOC);
			}
			catch (Exception $e) {
			}
		}

		echo json_encode($data, JSON_PRETTY_PRINT);

		return 0;
	}
}
