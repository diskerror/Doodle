<?php

namespace Music;

use Application\Command;
use ErrorException;
use Library\Exception\RuntimeException;


//function esc($v)
//{
////	return str_replace([' ', "'", '"', '(', ')'], ['\\ ', "\\'", '\\"', '\\(', '\\)'], $v);
////	return str_replace(["'"], ["\\\\'"], $v);
//	return escapeshellarg($v);
//}


class Apply extends Command
{
	/**
	 * Applies forScore meta-data to PDF file by file name.
	 *
	 * @return int
	 * @throws ErrorException
	 */
	public function main(): int
	{
		if (count($this->inputParams->arguments) === 0) {
			$this->help();
			return 0;
		}

		$meta = new PdfMetaDataList();
		$meta->loadDB();

		$output      = '';
		$result_code = 0;

		foreach ($this->inputParams->arguments as $argument) {
			$bname = basename($argument->arg);

			if ($meta->offsetExists($bname)) {
				$m = $meta[$bname];

				foreach ($m as &$v) {
					if ($v !== null) {
						$v = self::esc($v);
					}
				}

				$cmd = "exiftool -overwrite_original " .
					"-Title=$m->title -Author=$m->author -Subject=$m->subject -Keywords=$m->keywords " .
					self::esc($argument->arg);

				exec($cmd, $output, $result_code);

				if ($result_code) {
					throw new ErrorException($output, $result_code);
				}
				else {
					echo $bname, ' - done', PHP_EOL;
				}
			}
			else {
				throw new RuntimeException($bname . ' has no meta data');
			}
		}

		return 0;
	}

	protected static function esc(string $v): string
	{
		//	return str_replace([' ', "'", '"', '(', ')'], ['\\ ', "\\'", '\\"', '\\(', '\\)'], $v);
		//	return str_replace(["'"], ["\\\\'"], $v);
		return escapeshellarg($v);
	}

}
