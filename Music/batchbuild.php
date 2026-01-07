#!env php
<?php

use function Library\escapeshellarg;

include __DIR__ . '/../vendor/autoload.php';


if ($argc < 2) {
    echo 'need input';
    exit;
}

array_shift($argv);

foreach ($argv as $dirct) {
    $dirBase = basename($dirct);
    echo "Building $dirBase..." . PHP_EOL;

    $filelist = glob("$dirct/*");
    $blank    = (substr($filelist[0], -6, 2) % 2) == 1 ? ' -b' : '';

    $dirct   = escapeshellarg($dirct);
    $destPdf = escapeshellarg("~/Desktop/$dirBase.pdf");

    exec("music.php buildpdf $dirct/* $destPdf" . $blank);
    exec("music.php metadata $destPdf");
}
