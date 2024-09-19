#!/usr/bin/env php
<?php

include __DIR__ . '/PdfMetaData.php';
include __DIR__ . '/PdfMetaDataList.php';
include __DIR__ . '/../vendor/diskerror/src/DiskError.php';

function pr(string $s)
{
	fprintf(STDOUT, "%s\n", $s);
}

function er(string $s)
{
	fprintf(STDERR, "%s\n", $s);
}

function esc(&$v, $i = null)
{
	$v = escapeshellarg($v);
//	$v = str_replace(' ', '\\ ', $v);
//	$v = str_replace("'", "\\'", $v);
//	$v = str_replace('"', '\\"', $v);
}

//	First item is this script's name. Don't need it.
array_shift($argv);

mb_internal_encoding('UTF-8');
setlocale(LC_CTYPE, 'en_US.UTF-8');

//	The remainder are our directories to process.
foreach ($argv as $arg) {
	if (!is_file($arg)) {
		continue;
	}

	$meta = new PdfMetaDataList();
	$meta->loadDB();

//	echo json_encode($meta, JSON_PRETTY_PRINT);
//	exit;
//	print_r(array_keys($meta));

	$output      = '';
	$result_code = 0;
	$bname       = basename($arg);

	if ($meta->offsetExists($bname)) {
		$m = $meta[$bname];

		foreach ($m as &$v) {
			if ($v !== null) {
				$v = esc($v);
			}
		}

		$cmd = 'exiftool -overwrite_original ' .
			"-Title=$m->title -Author=$m->author -Subject=$m->subject -Keywords=$m->keywords " . esc($arg);

		exec($cmd, $output, $result_code);

		if ($result_code) {
			er('ERROR: ' . $cmd);
		}
		else {
			pr($bname . ' - done');
		}
	}
	else {
		er($bname . ' has no meta data');
	}

}
