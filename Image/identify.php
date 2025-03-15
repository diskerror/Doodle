#!/usr/bin/env php
<?php

function jsout($in)
{
    fprintf(STDOUT, '%s' . PHP_EOL . PHP_EOL, json_encode($in, JSON_PRETTY_PRINT));
}

array_shift($argv); //	First item is this script's name. Don't need it.
natsort($argv);

$im = new Imagick();

foreach ($argv as $fName) {
    $im = new Imagick($fName);
//    $im->resetIterator();
    $pages = $im->getNumberImages();

    for ($p = 0; $p < $pages; $p++) {
        $im->setIteratorIndex($p);
        $info             = array_merge(['index' => $p], $im->current()->identifyImage());
        $info['fileSize'] = (int)$info['fileSize'];
//		$info['pointSize'] = $im->current()->getPointSize();
        jsout($info);
    }

//    if ($im->readImage($fName)) {
//        $pages = $im->getNumberImages();
//
//        if ($pages === 1) {
//            $info             = array_merge(['index' => 0], $im->identifyImage());
//            $info['fileSize'] = (int)$info['fileSize'];
//            jsout($info);
//
//            $im->clear();
//        }
//        else {
//            for ($p = 0; $p < $pages; $p++) {
//                $im->readImage($fName . '[' . $p . ']');
//
//                $info             = array_merge(['index' => $p], $im->identifyImage());
//                $info['fileSize'] = (int)$info['fileSize'];
//                jsout($info);
//
//                $im->clear();
//            }
//        }
//    }
}
