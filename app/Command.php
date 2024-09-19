<?php

namespace Application;

use GetOptionKit\OptionResult;
use Library\StdIo;


/**
 * Abstract class for command classes.
 */
abstract class Command
{
	/**
	 * @var OptionResult
	 */
	protected OptionResult $inputParams;

	/**
	 * @param OptionResult $inputParams description
	 */
	public function __construct(OptionResult $inputParams)
	{
		$this->inputParams = $inputParams;
	}

	/**
	 * Describes the items in this command, else runs the command.
	 *
	 * @return int The exit code
	 */
	public function main(): int
	{
		return $this->help();
	}

	/**
	 * Describes the items in this command.
	 */
	public function help(): int
	{
		$descArr = (new Reflector(get_called_class()))->getFormattedDescriptions();

		if (count($descArr)) {
			StdIo::outln('Sub-commands:');
			foreach ($descArr as $description) {
				StdIo::outln("\t" . $description);
			}
		}

		return 0;
	}
}
