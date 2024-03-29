<?php

use Library\Commands;

class Ddl2DataDict extends Commands
{
	public static function main(): int
	{
		$argv = self::$opts->getArguments();

		if (!file_exists($argv[1])) {
			echo "File not found: $argv[1]\n";
			return 1;
		}

		if (!is_file($argv[1])) {
			echo "Not a file: $argv[1]\n";
			return 1;
		}

		$ddlLines = preg_split('/[\r\n]+/', file_get_contents($argv[1]));

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
