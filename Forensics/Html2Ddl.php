<?php

use Library\app\Commands;
use Library\DomDocParser;

class Html2Ddl extends Commands
{
	public static function main(): int
	{
		$fName = '';

		try {
			libxml_use_internal_errors(true);
			$ddp = new DomDocParser();

			$argv = self::$opts->getArguments();
			array_shift($argv);

			foreach ($argv as $fName) {
				if (in_array(basename($fName), ['summary.html', 'tables1.html'])) {
					continue;
				}

				$ddp->loadHTMLFile($fName);

//		echo Diskerror\prettify($dd->toTextArray()[1]), PHP_EOL;    //  0 is head, 1 is body
//		echo Diskerror\prettify($dd->toTextArray()[1][0][0][0][0]), PHP_EOL;
//		echo Diskerror\prettify($dd->toTextArray()[1][5][1]), PHP_EOL;
//		echo Diskerror\prettify($dd->toTextArray()[1][7][1]), PHP_EOL;
//		echo Diskerror\prettify($dd->toTextArray()[1][9][1]), PHP_EOL;
//		exit;

				$dbName = str_replace(['Schema ', ' '], ['', '_'], $ddp->toTextArray()[1][0][0][0][0]);

//  These may have the data we want, but we don't know which has been left out.
				for ($i = 5; $i < 15; $i += 2) {
					if (isset($ddp->toTextArray()[1][$i][1])) {
						$ofInterest = $ddp->toTextArray()[1][$i][1];

						//  The first item should be the caption.
						switch (true) {
							//  $i==5 will probably always be this:
							case (substr($ofInterest[0], 0, 17) === 'Columns of Table '):
								$fields = $ofInterest;
								break;

							case (substr($ofInterest[0], 0, 17) === 'Indexes on Table '):
								$indexes = $ofInterest;
								break;

							case (substr($ofInterest[0], 0, 37) === 'PK, UK, & Check Constraints on Table '):
								$pkuk = $ofInterest;
								break;

							case (substr($ofInterest[0], 0, 66) === 'Foreign Key Constraints on Table '):
								$foreign = $ofInterest;
								break;

							case (substr($ofInterest[0], -17) === ' does not have...'):
								$missing = $ofInterest;
								break;

							default;
								break;
						}
					}
				}


				$ddl = 'CREATE TABLE ' . self::fnFix($dbName) . '.' . self::fnFix(substr(array_shift($fields),
																						 17)) . " (\n";
				array_shift($fields);   //  skip over column names

				if ($f = array_shift($fields)) {    //  First item starts without comma.
					$ddl .=
						'    ' . self::fnFix($f[1]) . ' ' . self::typeFix($f[2]) . ($f[3] === 'Y' ? ' NOT NULL' : ' NULL') .
						(!in_array($f[4], [' ', ' ', '']) ? " DEFAULT '" . self::escQ($f[4]) . "'" : '') .
						(!in_array($f[5], [' ', ' ', '']) ? " COMMENT '" . self::escQ($f[5]) . "'" : '') . "\n";
				}

				foreach ($fields as $f) {
					$ddl .=
						'  , ' . self::fnFix($f[1]) . ' ' . self::typeFix($f[2]) . ($f[3] === 'Y' ? ' NOT NULL' : ' NULL') .
						(!in_array($f[4], [' ', ' ', '']) ? " DEFAULT '" . self::escQ($f[4]) . "'" : "") .
						(!in_array($f[5], [' ', ' ', '']) ? " COMMENT '" . self::escQ($f[5]) . "'" : "") . "\n";
				}

				if (isset($pkuk)) {
					if (count($pkuk) > 2) {
						array_shift($pkuk);  //  skip caption
						array_shift($pkuk);  //  skip column names
						foreach ($pkuk as $c) {
							if (is_array($c) && count($c) >= 5) {
								switch ($c[1]) {
									case 'Primary Key':
									case 'PRIMARY':
										$ddl .= '  , PRIMARY KEY ' . self::fnFix($c[0]) . ' (' . self::fnFix($c[4]) . ")\n";
										break;

									case 'Unique Key':
									case 'UNIQUE':
										$ddl .= '  , UNIQUE KEY ' . self::fnFix($c[0]) . ' (' . self::fnFix($c[4]) . ")\n";
										break;
								}
							}
						}
					}
				}

				if (isset($indexes)) {
					if (count($indexes) > 2) {
						array_shift($indexes);  //  skip caption
						array_shift($indexes);  //  skip column names
						foreach ($indexes as $idx) {
							if (is_array($idx) && count($idx) >= 5 && $idx[1] !== 'Check') {
								$ddl .= '  , ' . str_replace(['NON-UNIQUE', 'UNIQUE'], ['KEY', 'UNIQUE KEY'], $idx[0]) .
									' ' . self::fnFix($idx[1]) . ' (' . self::fnFix($idx[3]) . ")\n";
							}
						}
					}
				}

				if (isset($foreign)) {
					if (count($foreign) > 2) {
						array_shift($foreign);  //  skip caption
						array_shift($foreign);  //  skip column names
						foreach ($foreign as $fk) {
							if (is_array($fk) && count($fk) >= 9) {
								$ddl .= '  , CONSTRAINT ' . self::fnFix($fk[0]) . ' FOREIGN KEY (' . self::fnFix($fk[1]) .
									') REFERENCES ' . self::fnFix($fk[2]) . '.' . self::fnFix($fk[3]) . ' (' . self::fnFix($fk[4]) . ")\n";
							}
						}
					}
				}


				$ddl .= ");\n\n";

				echo $ddl;

				unset($fields, $pkuk, $indexes, $foreign, $missing);
			}
		}
		catch (Throwable $t) {
			fprintf(STDERR, "\n\n%s", $fName);
			fprintf(STDERR, "\n\n%s\n\n", $t);
			return 1;
		}

		return 0;
	}


	protected static function fnFix(string|array|null $f): string
	{
		if (is_array($f)) {
			$f = $f[0];
		}
		return $f === null ? '' : '"' . str_replace([', '], ['", "'], $f) . '"';
	}

	protected static function typeFix(?string $t): string
	{
		return $t === null ? '' : str_replace([' BYTE)', ' CHAR)'], [')', ')'], $t);
	}

	protected static function escQ(string $s): string
	{
		return str_replace("'", "\\'", $s);
	}


}
