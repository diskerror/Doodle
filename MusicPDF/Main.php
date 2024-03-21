<?php

namespace MusicPDF;

use GetOptionKit\OptionResult;
use Library\Commands;

class Main extends Commands
{

	public static function main(): int
	{
		echo __CLASS__ . ' main was called' . PHP_EOL;
		return 0;
	}
}
