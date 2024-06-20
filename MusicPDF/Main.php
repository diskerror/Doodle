<?php

namespace MusicPDF;

use Library\Commands;
use Library\PdfMetaDataList;

class Main extends Commands
{

	public static function main(): int
	{
		echo __CLASS__ . ' main was called' . PHP_EOL;
		return 0;
	}

	public static function apply()
	{
		print_r(self::$opts);
		echo PHP_EOL;exit;
		$meta = new PdfMetaDataList();
		$meta->loadDB();

		array_shift($argv);
		$output = null;
		$result_code = 0;

		foreach ($argv as $fName) {
			$bname = basename($fName);

			if ($meta->offsetExists($bname)) {
				$m = $meta[$bname];

				foreach ($m as &$v) {
					if ($v !== null) {
						$v = esc($v);
					}
				}

				$cmd = "exiftool -overwrite_original -Title={$m->title} -Author={$m->author} -Subject={$m->subject} -Keywords={$m->keywords} " . esc($fName);

				exec($cmd, $output, $result_code);

				if ($result_code) {
					echo 'ERROR: ', $cmd, PHP_EOL;
				} else {
					echo $bname, ' - done', PHP_EOL;
				}
			} else {
				echo $bname, ' has no meta data', PHP_EOL;
			}
		}
		return 0;
	}
}
