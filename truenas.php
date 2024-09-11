#!/usr/bin/env php
<?php

include 'vendor/autoload.php';

setlocale(LC_CTYPE, 'en_US.UTF-8');
mb_internal_encoding("UTF-8");

$db = new PDO('sqlite:/Volumes/WDBlack2/DataGripProjects/freenas/freenas-v1.db', SQLITE3_OPEN_READONLY);

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
