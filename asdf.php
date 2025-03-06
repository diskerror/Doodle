#!php
<?php

use Ds\Map;
//use \Composer\Autoload\ComposerStaticInitc741ea2452ed991d38b37ca862333390 as StaticInit;
//
//include 'vendor/composer/autoload_static.php';
//$loader = \Composer\Autoload\ComposerStaticInitc741ea2452ed991d38b37ca862333390::getLoader();

$m = new Map(['A', 'B', 'C', 'D']);

echo serialize($m), PHP_EOL;
