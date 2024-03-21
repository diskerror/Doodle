<?php

namespace Library;

use GetOptionKit\OptionResult;

/**
 * Abstract class for command classes.
 */
abstract class Commands
{
	public static $options = [];

	/**
	 * @var OptionResult
	 */
	protected static OptionResult $opts;

	/**
	 * A description of the entire PHP function.
	 *
	 * @param OptionResult $opts description
	 * @return void
	 */
	public static function init(OptionResult $opts): void
	{
		self::$opts = $opts;
	}

	/**
	 * @return int The exit code
	 */
	public static function main(): int
	{
		echo get_called_class() . ' main was called' . PHP_EOL;
		return 0;
	}
}
