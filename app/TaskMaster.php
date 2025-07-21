<?php

namespace Application;

use Library\StdIo;
use Phalcon\Cli\Task;

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
        $this->helpAction();
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

        $this->showOptions; //  actually calls the showOptions() method
    }
}
