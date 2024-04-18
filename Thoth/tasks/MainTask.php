<?php

use Service\Reflector;
use  Service\StdIo;

class MainTask extends TaskMaster
{
	/**
	 * Display list of commands, subcommands, and config structure.
	 */
	public function mainAction()
	{
		StdIo::outln(
			'Usage: [./]' . $this->config->process->name . '[.php] [command [sub-command] [arguments...]]'
		);
		StdIo::outln();

		foreach (glob(__DIR__ . '/*Task.php') as $fileName) {
			$className = basename($fileName, '.php');

			//	Skip this file.
			if ($className === 'MainTask') {
				continue;
			}

			$cmd = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', substr($className, 0, -4)));
			StdIo::outln("Command:\n\t" . $cmd . PHP_EOL);

			StdIo::outln('Sub-commands:');
			$refl = new Reflector($className);
			foreach ($refl->getFormattedDescriptions() as $description) {
				StdIo::outln("\t" . $description);
			}
			StdIo::outln();
		}
	}
}
