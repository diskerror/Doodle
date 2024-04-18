<?php
/**
 * Created by PhpStorm.
 * User: 3525339
 * Date: 11/21/2018
 * Time: 3:48 PM
 */

use Phalcon\Cli\Task;
use Service\Reflector;
use Service\StdIo;

/**
 * Class TaskMaster
 *
 * @property $config
 * @property $eventsManager
 * @property $logger
 * @property $pidHandler
 */
class TaskMaster extends Task
{
    /**
     * Describes the items in this command.
     */
    public function mainAction()
    {
        $reflector = new Reflector($this);

		StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
			StdIo::outln("\t" . $description);
        }
    }

    /**
     * Describes the items in this command.
     */
    public function helpAction()
    {
		$reflector = new Reflector($this);

		StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
			StdIo::outln("\t" . $description);
        }
    }
}
