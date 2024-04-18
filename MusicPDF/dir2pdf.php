#!/usr/bin/env php
<?php

function pr(string $s)
{
	fprintf(STDOUT, "%s\n", $s);
}

function er(string $s)
{
	fprintf(STDERR, "%s\n", $s);
}

function esc(&$v, $i=null)
{
//	$v = escapeshellarg($v);
	$v = str_replace(' ', '\\ ', $v);
	$v = str_replace("'", "\\'", $v);
	$v = str_replace('"', '\\"', $v);
}

///////////////////////////////////////////////////////////////////////////////
if ($argc < 3) {
	er('Needs source directory[ies] and destination directory.');
	er('Usage: ' . basename($argv[0]) . ' <input dir> [<input dir> | ...] <output dir>');
	exit(1);
}

//	Last item is output directory.
$destDir = array_pop($argv);
if (!is_dir($destDir)) {
	er('Output directory not found.');
	exit(1);
}

//	First item is this script's name. Don't need it.
array_shift($argv);

mb_internal_encoding("UTF-8");
setlocale(LC_CTYPE, "en_US.UTF-8");

//	The remainder are our directories to process.
foreach ($argv as $inputDir) {
	if (!is_dir($inputDir)) {
		er('"' . $inputDir . '" not found.');
		exit(1);
	}

//	chdir($inputDir);
	$inputFiles = glob("$inputDir/*.tif");
	natcasesort($inputFiles);

	$baseName = basename($inputDir);
    $destPath = "{$destDir}/{$baseName}.pdf";

	//  Only check the first file.
	//  We are assuming they are all the same.
	$im = new Imagick($inputFiles[0]);

	//  If more than one image in file then get the largest.
	$imCount = $im->getNumberImages();
	$im->setFirstIterator();
	$workRes = $im->getImageResolution()['x'];
	$frameNum = 0;
	if ($imCount > 1) {
		for ($i = 1; $i < $imCount; $i++) {
			$im->nextImage();
			$thisRes = $im->getImageResolution()['x'];
			if ($thisRes > $workRes) {
				$workRes = $thisRes;
				$frameNum = $i;
			}
		}
	}

	array_walk($inputFiles, 'esc');
    esc($destPath);

	$fileStr = implode("[$frameNum] ", $inputFiles) . "[$frameNum]";
//	$fileStr = implode(" ", $inputFiles);

	$densityStr = '';
//	if ($workRes < 480) {
//		$workRes = 480;
//		$densityStr = "-density 480x480 -units pixelsperinch";
//	}
	$resize = $workRes * 7.75;

//	$cmd = <<<CMD
//    magick $densityStr \
//    {$fileStr} \
//    -background white -deskew 90% \
//    -trim +repage -bordercolor white -border 0.4%x0.1% \
//    -adaptive-resize $resize -resample 480x480 \
//    -alpha off -blur 3x0.9 \
//    -depth 1 -compress Group4 "$destDir/$baseName.pdf"
//    CMD;

    // Input resolution or density.
	$RES = 800;

    // Total width in pixels.
	$RESZ = 7.75 * $RES;

    // Final resolution in pixels.
    $RESF = 480;

    $cmd = <<<CMD
    nice magick -density {$RES}x{$RES} -units pixelsperinch {$fileStr} \
    -alpha off -blur 3x0.9 \
    -threshold 68% \
    -background white -deskew 80% \
    -adaptive-resize {$RESZ} -resample {$RESF}x{$RESF} \
    -trim +repage -bordercolor white -border 5x2 \
    -threshold 50% -depth 1 \
    -compress Group4 $destPath
    CMD;

//	pr($cmd);

	exec($cmd);

	exec(__DIR__ . "/apply.php $destPath");
}
