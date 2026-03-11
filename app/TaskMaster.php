<?php

namespace Application;

use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Library\StdIo;
use ReflectionMethod;

/**
 * Base class for all Doodle CLI tasks.
 *
 * Provides option access, output helpers, and auto-generated help
 * via docblock reflection. No framework dependency.
 *
 * @property-read string $basePath
 * @property-read object $options
 * @property-read object $logger
 * @property-read object $config
 */
class TaskMaster
{
    /** Per-task CLI options (override in subclasses). */
    protected static array $taskOptions = [];

    /** Get per-task options (for App registration). */
    public static function getTaskOptions(): array
    {
        return static::$taskOptions;
    }

    /** Shared service container (set by App before dispatch). */
    private array $_services = [];

    /**
     * Inject a named service (called by App during bootstrap).
     */
    public function setService(string $name, mixed $value): void
    {
        $this->_services[$name] = $value;
    }

    /**
     * Magic getter — provides $this->basePath, $this->options, $this->logger, etc.
     */
    public function __get(string $name): mixed
    {
        return $this->_services[$name] ?? null;
    }

    /**
     * Get a parsed option value or a default if not set.
     */
    protected function getOption(string $name, mixed $default = null): mixed
    {
        return $this->_services['options']->$name ?? $default;
    }

    /**
     * Check if an option was provided.
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->_services['options']->$name);
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
     * Default action — shows help.
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

        // If child class is MainTask, show all project's tasks.
        if (str_ends_with($calledClass, 'MainTask')) {
            $classExplode = explode('\\', $calledClass);
            $basePath     = $this->_services['basePath'] ?? __DIR__ . '/..';
            foreach (glob($basePath . '/' . $classExplode[0] . '/*Task.php') as $taskFile) {
                $taskName  = basename($taskFile, 'Task.php');
                $className = $classExplode[0] . '\\' . basename($taskFile, '.php');
                if ($className !== $calledClass) {
                    StdIo::outln('');
                    StdIo::outln('    ' . $taskName);
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
    }

    protected function doOptions(): void
    {
        $possibleOptions = $this->_services['possibleOptions'] ?? null;
        if ($possibleOptions !== null && $possibleOptions->count()) {
            StdIo::outln("\tOption[s]:");
            StdIo::outln((new ConsoleOptionPrinter())->render($possibleOptions));
        }
    }
}
