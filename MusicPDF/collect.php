#!/usr/bin/env php82
<?php

include 'vendor/autoload.php';

$files = json_decode(shell_exec("exiftool -r -q -json '{$argv[1]}'"), JSON_THROW_ON_ERROR);

$fp = fopen(__DIR__ . '/meta_data.csv', 'wb');

include 'Meta.php';
$meta = new PdfMetaData();

fputcsv($fp, $meta->getPublicNames());

foreach ($files as $file) {
		$meta->assign($file);
		fputcsv($fp, $meta->toArray());
}

fclose($fp);
