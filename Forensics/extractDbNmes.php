#!/opt/local/bin/php72
<?php

/*
$argv[0]	name (not used)
$argv[1]	type (table, procedure, or view)
$argv[2]	path
*/

$type = $argv[1];

//  Put output file with examined items.
switch ($type) {
	case 'table':
		$outFile = $argv[2] . '/0ALL_TABLES.tsv';
		break;

	case 'procedure':
		$outFile = $argv[2] . '/0ALL_PROCEDURES.tsv';
		break;

	case 'view':
		$outFile = $argv[2] . '/0ALL_VIEWS.tsv';
		break;

	default:
		fwrite(STDERR, "bad type\n");
}

//  Grab all file names in directory that end in ".sql".
$sqlFiles = glob($argv[2] . '/*.sql');
sort($sqlFiles);

//  Create file or erase existing file
file_put_contents($outFile, '');

foreach ($sqlFiles as $sqlFile) {
	$sql   = file_get_contents($sqlFile);
	$match = ['', '', ''];

	//  Look for table, procedure, or view name assuming SQL Server syntax, case insensitive.
	switch ($type) {
		case 'table':
			preg_match(         '/CREATE\s+TABLE\s+(?:(?:\[dbo\]\.|)\[([\w.]+)\]|(?:dbo\.|)([\w.]+)).*/si', $sql, $match);
			break;

		case 'procedure':
			preg_match('/CREATE\s+PROC(?:EDURE|)\s+(?:(?:\[dbo\]\.|)\[([\w.]+)\]|(?:dbo\.|)([\w.]+)).*/si', $sql, $match);
			break;

		case 'view':
			preg_match(          '/CREATE\s+VIEW\s+(?:(?:\[dbo\]\.|)\[([\w.]+)\]|(?:dbo\.|)([\w.]+)).*/si', $sql, $match);
			break;
	}

	//  Only #1 or #2 will have results. The other will have an empty string.
	//  Any problems with the above match statements will cause a blank line to be inserted here.
	file_put_contents($outFile, basename($sqlFile) . "\t" . $match[1] . $match[2] . "\n", FILE_APPEND);
}
