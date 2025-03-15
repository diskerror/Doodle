#!/usr/bin/env php
<?php

# Convert to 1-bit file.
# convert in.tif -monochrome -depth 1 out.tif

if ($argc < 2) {
	echo "need input";
	exit;
}

array_shift($argv);

foreach ($argv as $fName) {
	if (!file_exists($fName)) {
		echo "bad input file: $fName", PHP_EOL;
		continue;
	}

	exec("(nice magick \"$fName\" -threshold 50% -depth 1 -compress Group4 \"$fName\")&");
}

echo PHP_EOL;
