#!/usr/bin/env php
<?php

include __DIR__ . '/../vendor/autoload.php';

use MusicPDF\PdfMetaDataList;

setlocale(LC_CTYPE, 'en_US.UTF-8');
mb_internal_encoding("UTF-8");

function esc($v)
{
//	return str_replace([' ', "'", '"', '(', ')'], ['\\ ', "\\'", '\\"', '\\(', '\\)'], $v);
//	return str_replace(["'"], ["\\\\'"], $v);
	return escapeshellarg($v);
}

$meta = new PdfMetaDataList();
//$meta->loadFile(__DIR__ . '/meta_data.tsv');
$meta->loadDB();

//echo json_encode($meta, JSON_PRETTY_PRINT);
//exit;
//print_r(array_keys($meta));

array_shift($argv);
$output = null;
$result_code = 0;

foreach ($argv as $fName) {
	$bname = basename($fName);

	if ($meta->offsetExists($bname)) {
		$m = $meta[$bname];

		foreach ($m as &$v) {
			if ($v !== null) {
				$v = esc($v);
			}
		}

		$cmd = "exiftool -overwrite_original -Title={$m->title} -Author={$m->author} -Subject={$m->subject} -Keywords={$m->keywords} " . esc($fName);

		exec($cmd, $output, $result_code);

		if ($result_code) {
			echo 'ERROR: ', $cmd, PHP_EOL;
		} else {
			echo $bname, ' - done', PHP_EOL;
		}
	} else {
		echo $bname, ' has no meta data', PHP_EOL;
	}
}
