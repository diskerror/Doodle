#!/usr/bin/env php
<?php

use Application\App;
use Library\Exception\RuntimeException;
use Library\StdIo;
use Music\PdfMetaData;

require 'vendor/diskerror/autoload/autoload.php';

$app = new App(__DIR__);
$app->parseArgv($argv);


if (count($app->inputParams->arguments) === 0) {
	StdIo::outln(basename($argv[0]) . ' [options] <file...>');
    $app->showOptions();
    exit(0);
}

$db = new SQLite3(__DIR__ . '/music.sqlite');
$db->enableExceptions(true);

foreach ($app->inputParams->arguments as $argument) {
    if (!is_file($argument->arg)) {
        throw new RuntimeException('Not a file.' . PHP_EOL . '  ' . $argument->arg);
    }

    $output      = '';
    $result_code = 0;
    $bname       = basename($argument->arg);

    $meta = new PdfMetaData(
        $db->querySingle("SELECT title, author, subject, keywords FROM meta WHERE filename = '$bname'", true)
    );

    if ($meta->title !== '') {
        foreach ($meta as &$v) {
            if ($v !== null) {
                $v = escapeshellarg($v);
            }
        }
        $efile = escapeshellarg($argument->arg);

        $cmd = 'exiftool -overwrite_original ' .
            "-Title=$meta->title -Author=$meta->author -Subject=$meta->subject -Keywords=$meta->keywords $efile";

//        StdIo::outln($cmd);
//        continue;
        exec($cmd, $output, $result_code);

        if ($result_code) {
            throw new RuntimeException('ERROR: ' . $cmd . PHP_EOL . $output . PHP_EOL . $result_code);
        }
        else {
            StdIo::outln($bname . ' - done');
        }
    }
    else {
        throw new RuntimeException('File has no meta data.' . PHP_EOL . '  ' . $argument->arg);
    }
}
