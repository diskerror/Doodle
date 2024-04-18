#!/usr/bin/env php
<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

ini_set('memory_limit', -1);

////////////////////////////////////////////////////////////////////////////////////////////////////
try {
	//  Remove program name.
	array_shift($argv);

	//  Fix $argv indexing.
	$argv = array_values($argv);

	if (count($argv) === 0) {
		$path = getcwd();
	} else {
		$path = $argv[0];
	}

	$path = realpath($path);

	if (!file_exists($path)) {
		throw new InvalidArgumentException('The path "' . $path . '" does not exist.');
	}

	$fileItr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

	foreach ($fileItr as $file) {
		$longName = $file->getPathname();
		$matches = [];
		if (preg_match('/^(.*IMG_\d{4})_\d{5}.CR2$/', $longName, $matches) == 1) {
			$shortName = $matches[1] . '.CR2';
			if (!file_exists($shortName)) {
//				fwrite(STDOUT, $longName . '  RENAME' . PHP_EOL);
		        rename($longName, $shortName);
			} elseif (filesize($longName) === filesize($shortName)) {
//				fwrite(STDOUT, $longName . '  UNLINK' . PHP_EOL);
				unlink($longName);
			}
		}
	}
//	print_r($dir->files);
} catch (Throwable $t) {
	fwrite(STDERR, $t);
}
