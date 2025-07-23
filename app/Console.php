<?php

namespace Application;

use Phalcon\Cli\Dispatcher\Exception as DispatcherException;

class Console extends \Phalcon\Cli\Console
{
    public function handle(?array $parsedArgv = []): void
    {
        switch (count($parsedArgv)) {
            case 0:
                parent::handle(['task' => 'main', 'action' => 'main', 'params' => []]);
                break;

            case 1:
                try {
                    parent::handle(['task' => $parsedArgv[0], 'action' => 'main', 'params' => []]);
                }
                catch (DispatcherException) {
                    parent::handle(['task' => 'main', 'action' => 'main', 'params' => $parsedArgv]);
                }
                break;

            default:
                try {
                    parent::handle(
                        [
                            'task' => $parsedArgv[0],
                            'action' => $parsedArgv[1],
                            'params' => array_slice($parsedArgv, 2),
                        ]);
                }
                catch (DispatcherException) {
                    try {
                        parent::handle(
                            [
                                'task' => $parsedArgv[0],
                                'action' => 'main',
                                'params' => array_slice($parsedArgv, 1),
                            ]);
                    }
                    catch (DispatcherException) {
                        parent::handle(['task' => 'main', 'action' => 'main', 'params' => $parsedArgv]);
                    }
                }
                break;
        }
    }
}
