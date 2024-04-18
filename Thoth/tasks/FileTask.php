<?php

use Resource\Paths;
use Structure\StringArray;
use Service\StdIo;

class FileTask extends TaskMaster
{
	/**
	 * Compare file basenames under two paths.
	 */
	function NameCompareAction($path1, $path2)
	{
		$pathsToIgnore = new StringArray(file(
			$this->basePath . '/data/dotNetIgnore.txt',
			FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
		));

		$list1 = new Paths($pathsToIgnore);
		$list2 = new Paths($pathsToIgnore);

		$list1->loadFileNames($path1);
		$list2->loadFileNames($path2);

//		fwrite(STDOUT, 'Comparing file names' . PHP_EOL);
//		if (array_key_exists('o', $_SERVER['opts'])) {
//			$fp = fopen($_SERVER['opts']['o'], 'w');
//		}
//		else {
		$fp = STDOUT;
//		}

		$result = [];
		foreach ($list1->files as $firstFile => $basename) {
			$foundFiles = array_keys($list2->files, $basename);
			foreach ($foundFiles as $secondFile) {
//			fwrite($fp, $firstFile . PHP_EOL);
			fwrite($fp, $firstFile . "\t" . $secondFile . "\t" . $basename . PHP_EOL);
//				++$result[dirname($firstFile) . "\t" . dirname($secondFile)];
			}
		}

//		foreach ($result as $k => $v) {
//			if ($v > 2) {
//				fwrite($fp, $k . "\t" . $v . PHP_EOL);
//			}
//		}

		if ($fp !== STDOUT) {
			fclose($fp);
		}
	}

	/**
	 * Get size of all files under path.
	 */
	function SizeAction($pathRoot)
	{
		ini_set('memory_limit', -1);

		$paths = new Paths(new StringArray(file(
			$this->basePath . '/data/dotNetIgnore.txt',
			FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
		)));

		$pathRoot = realpath($pathRoot);
		$baseLen  = strlen(basename($pathRoot));
		$paths->loadFileNames($pathRoot);
		foreach ($paths as $path => $baseName) {
			StdIo::outln($path . "\t" . filesize($pathRoot . substr($path, $baseLen)));
		}
	}

}
