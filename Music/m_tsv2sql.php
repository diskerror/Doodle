#!/usr/bin/env php82
<?php

include 'vendor/autoload.php';

setlocale(LC_CTYPE, 'en_US.UTF-8');
mb_internal_encoding("UTF-8");

$db = new SQLite3(__DIR__ . '/music.sqlite');
$db->exec('DROP TABLE if EXISTS meta');
$db->exec('CREATE TABLE meta (
    meta_id INTEGER PRIMARY KEY,
    filename text,
    title text,
    author text,
    subject text,
    keywords text,
    rating INTEGER,
    difficulty INTEGER,
    duration INTEGER,
    keysf INTEGER,
    keymi INTEGER)
');
$db->exec('CREATE UNIQUE INDEX idx_filename ON meta (filename)');
$db->exec('CREATE INDEX idx_title ON meta (title)');
$db->exec('CREATE INDEX idx_author ON meta (author)');
$db->exec('CREATE INDEX idx_subject ON meta (subject)');
$db->exec('CREATE INDEX idx_keywords ON meta (keywords)');

$meta = new PdfMetaDataList();
$meta->loadFile(__DIR__ . '/meta_data.tsv');

foreach ($meta as $m) {
	$db->exec('
		INSERT INTO meta (
			filename,
			title,
			author,
			subject,
			keywords
		) VALUES (
			"' . $m->FileName . '",
			"' . $m->Title . '",
			"' . $m->Author . '",
			"' . $m->Subject . '",
			"' . $m->Keywords . '"
		)
	');
}

$db->close();
