<?php

namespace Logic;

use Service\StdIo;

class Csv
{
	/**
	 * @param string $fname
	 * @param int    $hRowNum
	 *
	 * @return array
	 */
	public static function toArray(string $fname, int $hRowNum = 0): array
	{
		$fp = fopen($fname, 'rb');
		self::skipBOM($fp);

		//	skip rows
		for ($h = 0; $h < $hRowNum; ++$h) {
			fgetcsv($fp);
		}

		$header = fgetcsv($fp);
		$hCount = count($header);

		$data = [];
		while (!feof($fp)) {
			$r = fgetcsv($fp);
			if (count($r) === $hCount) {
				$data[] = array_combine($header, $r);
			}
		}

		fclose($fp);

		return $data;
	}

	/**
	 * Move file pointer past UTF-8 BOM, if any
	 * or place at beginning of file.
	 *
	 * @param $fp
	 *
	 * @return void
	 */
	public static function skipBOM($fp): void
	{
		$bom = fgets($fp);
		if (substr($bom, 0, 3) === "\xEF\xBB\xBF") {
			fseek($fp, 3);
		}
		else {
			fseek($fp, 0);
		}
	}
}
