#!/usr/bin/env php
<?php

use Music\PdfMetaData;

require __DIR__ . '/../vendor/diskerror/autoload/autoload.php';

//require __DIR__ . '/PdfMetaData.php';
//require __DIR__ . '/PdfMetaDataList.php';

function pr(string $s)
{
    fprintf(STDOUT, "%s\n", $s);
}

function er(string $s)
{
    fprintf(STDERR, "%s\n", $s);
}

//	First item is this script's name. Don't need it.
array_shift($argv);

mb_internal_encoding('UTF-8');
setlocale(LC_CTYPE, 'en_US.UTF-8');

$db = new SQLite3(__DIR__ . '/music.sqlite');
$db->enableExceptions(true);

//	The remainder are our directories to process.
foreach ($argv as $arg) {
    if (!is_file($arg)) {
        continue;
    }

    $output      = '';
    $result_code = 0;
    $bname       = basename($arg);

    $m = new PdfMetaData(
        $db->querySingle("SELECT title, author, subject, keywords FROM meta WHERE filename = '$bname'", true)
    );

    if ($m->title !== '') {
        foreach ($m as &$v) {
            if ($v !== null) {
                $v = escapeshellarg($v);
            }
        }
        $arg = escapeshellarg($arg);

        $cmd = 'exiftool -overwrite_original ' .
            "-Title=$m->title -Author=$m->author -Subject=$m->subject -Keywords=$m->keywords $arg";

//        pr($cmd);
//        continue;
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
