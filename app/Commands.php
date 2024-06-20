<?php

namespace Library\app;

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
	 * @param OptionResult $opts description
	 * @return void
	 */
	public static function init(OptionResult $opts): void
	{
		self::$opts = $opts;
	}

	/**
	 * Describes the items in this command, else runs the command.
	 *
	 * @return int The exit code
	 */
	public static function main(): int
	{
        $reflector = new Reflector(get_calling_class());

		StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
			StdIo::outln("\t" . $description);
        }

		return 0;
	}

    /**
     * Describes the items in this command.
     */
    public static function help()
    {
		$reflector = new Reflector(get_calling_class());

		StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
			StdIo::outln("\t" . $description);
        }
    }
}
