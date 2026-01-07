<?php

use Application\TaskMaster;

class Ddl2DataDictTask extends TaskMaster
{
    /**
     * Converts a DDL to a data dictionary.
     *
     * @return int
     * @throws ErrorException
     */
    public function mainAction(...$args): void
	{
		$fileStr = $this->options->arguments[0]->arg;

		if (!file_exists($fileStr)) {
			echo "File not found: $fileStr\n";
			return;
		}

		if (!is_file($fileStr)) {
			echo "Not a file: $fileStr\n";
			return;
		}

		$ddlLines = preg_split('/[\r\n]+/', file_get_contents($fileStr));

		$tableMatches = [];
		while (($line = array_shift($ddlLines)) !== null) {
			if (preg_match('/^CREATE TABLE \[(\w+)\]\.\[(\w+)\].*$/', $line, $tableMatches) === 1) {

				$table = $tableMatches[1] . '.' . self::camelToSnake($tableMatches[2]);
//				$table = self::camelToSnake($tableMatches[2]);

				$fieldMatches = [];
				while (
					($fieldDef = array_shift($ddlLines)) !== null &&
					preg_match('/^\s+\[(\w+)\] \[\w+\].*$/', $fieldDef, $fieldMatches) === 1
				) {
					echo $table, '.', self::camelToSnake(substr($fieldMatches[1], 3)), PHP_EOL;
				}
			}
		}
	}

	/**
	 * Converts a given string from camel case to snake case.
	 *
	 * @param string $s The input string in camel case.
	 * @return string The converted string in snake case.
	 */
	private static function camelToSnake(string $s): string
	{
		return preg_replace(
			[
				'/([A-Z])([A-Z][a-z])/',
				'/([A-Z][a-z])([A-Z])/',
				'/([a-z])([A-Z][a-z_])/',
				'/([a-z])([A-Z][A-Z])/',
				'/([a-z])([0-9][A-Z])/',
			],
			'$1_$2',
			$s
		);
	}


}
