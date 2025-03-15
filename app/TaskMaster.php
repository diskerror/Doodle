<?php

namespace Application;

use Phalcon\Cli\Task;
use Application\Reflector;
use Library\StdIo;

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
        $reflector = new Reflector(get_called_class());

        StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
            StdIo::outln("\t" . $description);
        }
    }

    /**
     * Describes the items in this command.
     */
    public function helpAction(): void
    {
        $reflector = new Reflector(get_called_class());

        StdIo::outln('Sub-commands:');
        foreach ($reflector->getFormattedDescriptions() as $description) {
            StdIo::outln("\t" . $description);
        }
    }
}
