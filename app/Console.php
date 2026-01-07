<?php

namespace Application;

use Phalcon\Cli\Dispatcher\Exception as DispatcherException;

class Console extends \Phalcon\Cli\Console
{
    public function handle(?array $parsedArgv = []): void
    {
        if (count($parsedArgv) > 0) {
            $this->checkConflict($parsedArgv[0]);
        }

        switch (count($parsedArgv)) {
            case 0:
                parent::handle(['task' => 'main', 'action' => 'main', 'params' => []]);
                break;

            case 1:
                try {
                    parent::handle(['task' => $parsedArgv[0], 'action' => 'main', 'params' => []]);
                }
                catch (DispatcherException) {
                    try {
                        parent::handle(['task' => 'main', 'action' => $parsedArgv[0], 'params' => []]);
                    }
                    catch (DispatcherException) {
                        parent::handle(['task' => 'main', 'action' => 'main', 'params' => $parsedArgv]);
                    }
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
                        try {
                            parent::handle(
                                [
                                    'task'   => 'main',
                                    'action' => $parsedArgv[0],
                                    'params' => array_slice($parsedArgv, 1),
                                ]);
                        }
                        catch (DispatcherException) {
                            parent::handle(['task' => 'main', 'action' => 'main', 'params' => $parsedArgv]);
                        }
                    }
                }
                break;
        }
    }

    private function checkConflict(string $name): void
    {
        $di = $this->getDI();
        if (!$di->has('dispatcher')) {
            return;
        }
        $dispatcher = $di->getShared('dispatcher');
        $namespace  = $dispatcher->getNamespaceName();

        // Check if it exists as a Task
        // Convention: [Namespace]\[Name]Task
        $taskClass = $namespace . '\\' . ucfirst($name) . 'Task';
        $isTask    = class_exists($taskClass);

        // Check if it exists as an Action in MainTask
        // Convention: [Namespace]\MainTask
        $mainTaskClass = $namespace . '\\MainTask';
        $actionMethod  = $name . 'Action';
        $isAction      = class_exists($mainTaskClass) && method_exists($mainTaskClass, $actionMethod);

        if ($isTask && $isAction) {
            throw new DispatcherException(
                sprintf(
                    "Ambiguous command '%s'.\nIt exists as a Task: %s\nAnd as an Action: %s::%s",
                    $name,
                    $taskClass,
                    $mainTaskClass,
                    $actionMethod
                )
            );
        }
    }
}
