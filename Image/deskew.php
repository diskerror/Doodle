#!/usr/bin/env php
<?php

// Image/deskew.php inputFile outputFile

if ($argc < 3) {
    echo "need proper input";
    exit;
}

$inputFile  = $argv[1];
$outputFile = $argv[2];

$magick = new Imagick();

echo "Scanning for image in file\n";
if ($magick->readImage($inputFile)) {
    $pages = $magick->getNumberImages();

    if ($pages > 1) {
        for ($p = 0; $p < $pages; $p++) {
            $magick->readImage($fName . '[' . $p . ']');
            $info = $magick->identifyImage();
            if (((int)$info['fileSize']) > 0) {
                break;
            }
            $magick->clear();
        }
    }
}

echo "Deskewing image\n";
$magick->deskewImage(80);
echo "Trimming image\n";
$magick->trimImage(0);
echo "Adding border\n";
$magick->borderImage('white', 10, 25);
echo "Writing image to file\n";
$magick->writeImage($outputFile);
