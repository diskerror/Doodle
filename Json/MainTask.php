<?php

namespace Json;

use Application\TaskMaster;
use Library\Json;
use Library\StdIo;

class MainTask extends TaskMaster
{
	/**
	 * Default action.
	 */
//    public function mainAction(): void
//    {
//        $this->info("Hello from Json!");
//    }

	/**
	 * Sort keys.
	 * If array is a list then sort values.
	 */
	public function sortKeysAction(...$in): void
	{
		if (count($in) != 1) {
			StdIo::err('Need file name. One file.');
			return;
		}

		if (!file_exists($in[0])) {
			StdIo::err("File doesn't exist.");
			return;
		}

		$j = Json::decode(file_get_contents($in[0]));
		$this->sortAllKeys($j);
		StdIo::jsonOut($j);
	}

	private function sortAllKeys(array &$a): void
	{
		ksort($a, SORT_NATURAL);

		foreach ($a as &$m) {
			switch (gettype($m)) {
				case 'object':
					$m = (array)$m;
				case 'array':
					if (array_is_list($m)) {
						sort($m, SORT_NATURAL);
					}
					else {
						$this->sortAllKeys($m);
					}
				break;

				default:
				break;
			}
		}
	}
}