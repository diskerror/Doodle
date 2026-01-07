<?php

namespace Application;

use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Library\StdIo;
use Phalcon\Cli\Task;
use ReflectionMethod;

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
     * Get a parsed option value or a default if not set.
     */
    protected function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options->$name ?? $default;
    }

    /**
     * Check if an option was provided.
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->options->$name);
    }

    /**
     * Print a success message.
     */
    protected function success(string $message): void
    {
        StdIo::success($message);
    }

    /**
     * Print an info message.
     */
    protected function info(string $message): void
    {
        StdIo::info($message);
    }

    /**
     * Print a warning message.
     */
    protected function warn(string $message): void
    {
        StdIo::warn($message);
    }

    /**
     * Print an error message.
     */
    protected function fail(string $message): void
    {
        StdIo::fail($message);
    }

    /**
     * Ask the user for confirmation (y/n).
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        StdIo::out("{$question} {$suffix} ");
        $input = trim(StdIo::in(10));

        if (empty($input)) {
            return $default;
        }

        return strtolower($input[0]) === 'y';
    }

    /**
     * By default, describes the items in this command.
     * @return void
     */
    public function mainAction(...$args): void
    {
        $this->helpAction();
    }

    /**
     * Describes the items in this command.
     */
    public function helpAction(): void
    {
        $calledClass = get_called_class();
        $this->doReflection($calledClass);

        // If child class is MainTask the show all project's tasks.
        if (str_ends_with($calledClass, 'MainTask')) {
            $classExplode = explode('\\', $calledClass);
            foreach (glob($this->basePath . '/' . $classExplode[0] . '/*Task.php') as $taskFile) {
                $taskName  = basename($taskFile, 'Task.php');
//                $taskName  = preg_replace('/(?<!\ )[A-Z]/', '-$0', $taskName);
//                $taskName  = substr($taskName, 0, 1) === '-' ? substr($taskName, 1) : $taskName;
//                $taskName  = strtolower($taskName);
                $className = $classExplode[0] . '\\' . basename($taskFile, '.php');
                if ($className !== $calledClass) {
                    StdIo::outln('    ' . /*($taskName === 'main' ? '[default]' :*/ $taskName/*)*/);
                    $this->doReflection($className);
                }
            }
        }
        $this->doOptions();
    }

    protected static function doReflection(string $calledClass): void
    {
        if (!method_exists($calledClass, 'mainAction') ||
            $calledClass !== (new ReflectionMethod($calledClass, 'mainAction'))->class
        ) {
            return;
        }

        $reflector = new Reflector($calledClass);

        foreach ($reflector->getFormattedDescriptions() as $description) {
            StdIo::outln("\t" . $description);
        }

        return;
    }

    protected function doOptions(): void
    {
        if ($this->possibleOptions->count()) {
            StdIo::outln("\tOption[s]:");
            StdIo::outln((new ConsoleOptionPrinter())->render($this->possibleOptions));
        }
    }
}
