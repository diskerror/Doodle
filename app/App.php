<?php

namespace Application;

use ErrorException;
use Exception;
use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionResult;
use ReflectionMethod;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class App
{
    private string           $basePath;
    private OptionCollection $possibleOptions;
    private OptionResult     $options;
    private ?string          $namespace;
    private Application      $application;

    // -----------------------------------------------------------------------
    // Error / signal handlers (unchanged from original)
    // -----------------------------------------------------------------------

    public static function _errorHandler($errno, $message, $fname, $line): void
    {
        throw new ErrorException($message, $errno, E_ERROR, $fname, $line);
    }

    public static function _exceptionHandler(Throwable $t): void
    {
        fprintf(STDERR, '%s' . PHP_EOL, $t->getMessage());
        fprintf(STDERR, '%s (Line: %d)' . PHP_EOL, $t->getFile(), $t->getLine());
        if (isset($GLOBALS['doodle_debug']) && $GLOBALS['doodle_debug']) {
            fprintf(STDERR, '%s' . PHP_EOL, $t->getTraceAsString());
        }
        exit($t->getCode());
    }

    public static function _shutdownHandler(): void
    {
        $lastError = error_get_last();
        if ($lastError !== null && $lastError['type'] === E_ERROR) {
            fprintf(STDERR, 'Uncaught exception: %s' . PHP_EOL, $lastError['message']);
            fprintf(STDERR, '%s:%s' . PHP_EOL, $lastError['file'], $lastError['line']);
        }
    }

    public static function _signalHandler(int $signo, mixed $siginfo): void
    {
        fprintf(STDERR, 'Received signal: %d' . PHP_EOL, $signo);
        exec('pkill -P ' . posix_getpid());
        exit($signo);
    }

    /**
     * App constructor.
     */
    public function __construct()
    {
        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        ini_set('error_reporting', E_ALL);
        set_error_handler([self::class, '_errorHandler']);
        set_exception_handler([self::class, '_exceptionHandler']);
        register_shutdown_function([self::class, '_shutdownHandler']);
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGQUIT, SIGTSTP] as $signal) {
            try {
                pcntl_signal($signal, [self::class, '_signalHandler']);
            }
            catch (Exception) {
            }
        }
        pcntl_async_signals();

        $this->basePath = realpath(__DIR__ . '/..');

        // Options setup (GetOptionKit)
        $this->possibleOptions = new OptionCollection();
        $appOptionsFile        = $this->basePath . '/app/options.php';
        if (file_exists($appOptionsFile)) {
            $this->addOptions(include $appOptionsFile);
        }
        if (array_key_exists('options', $GLOBALS)) {
            $this->addOptions($GLOBALS['options']);
        }

        $this->application = new class('doodle', '2.0.0') extends Application {
            public function extractNamespace(string $name, ?int $limit = null): string
            {
                $ns = parent::extractNamespace($name, $limit);
                return ucfirst($ns);
            }
        };
    }

    private function addOptions(array $optsIn): void
    {
        foreach ($optsIn as $opt) {
            $option = new Option($opt['spec']);
            unset($opt['spec']);

            // Skip if already registered (multiple tasks may share the same option)
            $id = $option->long ?? $option->short ?? null;
            if ($id !== null && $this->possibleOptions->find($id) !== null) {
                continue;
            }

            foreach ($opt as $optKey => $optValue) {
                $option->$optKey($optValue);
            }
            $this->possibleOptions->addOption($option);
        }
    }

    /**
     * Run the application.
     *
     * @param array $argv Raw CLI arguments.
     */
    public function run(array $argv): void
    {
        // Determine namespace from script filename
        $scriptName = basename($argv[0], '.php');
        if (strtolower($scriptName) === 'doodle') {
            $this->namespace = null; // discover all namespaces
        }
        else {
            $this->namespace = ucwords($scriptName, '.,-_+');
        }

        // Auto-discover commands and collect task-level options
        $this->discoverCommands();

        // Parse GetOptionKit options (global + task-level, for backward compat)
        $this->options           = (new OptionParser($this->possibleOptions))->parse($argv);
        $GLOBALS['doodle_debug'] = (bool)($this->options->debug ?? false);

        // Run Symfony Console
        $this->application->run();
    }

    /**
     * Discover *Task.php files and register them as Symfony Commands.
     */
    private function discoverCommands(): void
    {
        if ($this->namespace !== null) {
            // Per-project script: discover only from one namespace
            $this->discoverNamespace($this->namespace, false);
        }
        else {
            // Universal 'doodle' script: discover all namespaces
            foreach (glob($this->basePath . '/[A-Z]*', GLOB_ONLYDIR) as $dir) {
                $ns = basename($dir);
                if ($ns === 'Application') {
                    continue;
                } // skip app/ infrastructure
                $this->discoverNamespace($ns, true);
            }
        }
    }

    /**
     * Discover Task classes in a namespace directory and register as Commands.
     *
     * @param string $namespace The namespace (directory name, e.g. "Xml")
     * @param bool $prefix Whether to prefix command names with "namespace:"
     */
    private function discoverNamespace(string $namespace, bool $prefix): void
    {
        $dir = $this->basePath . '/' . $namespace;
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*Task.php') as $file) {
            $className = $namespace . '\\' . basename($file, '.php');
            if (!class_exists($className)) {
                continue;
            }
            if (!is_subclass_of($className, TaskMaster::class)) {
                continue;
            }

            $taskBase    = basename($file, 'Task.php'); // e.g. "StripMusic", "Main"
            $commandName = $this->taskNameToCommandName($taskBase);

            $isDefault = ($taskBase === 'Main');

            if ($prefix) {
                // MainTask → just the namespace name (e.g. "Music" not "Music:main")
                $fullName = $isDefault ? strtolower($namespace) : strtolower($namespace) . ':' . $commandName;
            }
            else {
                $fullName = $commandName;
            }

            // Merge task-specific options into the global collection
            $this->addOptions($className::getTaskOptions());

            $command = $this->createCommand($fullName, $className, $isDefault);
            $this->application->addCommand($command);

            if ($isDefault && !$prefix) {
                $this->application->setDefaultCommand($fullName, false);
            }
        }
    }

    /**
     * Convert a Task class base name to a kebab-case command name.
     * e.g. "StripMusic" → "strip-music", "BuildPdf" → "build-pdf", "Main" → "main"
     */
    private function taskNameToCommandName(string $taskBase): string
    {
        // Insert hyphens before uppercase letters, then lowercase
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $taskBase);
        return strtolower($kebab);
    }

    /**
     * Create a Symfony Command that delegates to a TaskMaster subclass.
     */
    private function createCommand(string $name, string $className, bool $isDefault): Command
    {
        $app = $this;

        $command = new class($name, $className, $app) extends Command {
            private string $taskClass;
            private App    $app;

            public function __construct(string $name, string $taskClass, App $app)
            {
                $this->taskClass = $taskClass;
                $this->app       = $app;
                parent::__construct($name);
            }

            protected function configure(): void
            {
                $this->setDescription($this->getTaskDescription());

                // Accept any number of arguments (passed through to mainAction)
                $this->addArgument('task_args',
                                   InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                                   'Arguments passed to the task');

                // Register GetOptionKit options as Symfony options for --help display
                foreach ($this->app->getPossibleOptions() as $opt) {
                    $optName = $opt->long ?? $opt->short ?? null;
                    if ($optName === null) {
                        continue;
                    }
                    if (in_array($optName, ['help', 'verbose', 'quiet', 'version'])) {
                        continue;
                    } // Symfony built-ins
                    $short = $opt->short && strlen($opt->short) === 1 ? $opt->short : null;
                    $mode  = $opt->isa === 'boolean' ? InputOption::VALUE_NONE : InputOption::VALUE_OPTIONAL;
                    $this->addOption($optName, $short, $mode, $opt->desc ?? '');
                }
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $args = $input->getArgument('task_args');

                // Check if first arg is an action name (e.g. "help")
                $action = 'mainAction';
                if (!empty($args)) {
                    $possibleAction = $args[0] . 'Action';
                    if (method_exists($this->taskClass, $possibleAction)) {
                        $action = $possibleAction;
                        array_shift($args);
                    }
                }

                // Instantiate the task and inject services
                $task = new ($this->taskClass)();
                $this->app->injectServices($task);

                // Call the action
                $task->$action(...$args);

                return Command::SUCCESS;
            }

            private function getTaskDescription(): string
            {
                try {
                    $ref = new ReflectionMethod($this->taskClass, 'mainAction');
                    $doc = $ref->getDocComment();
                    if ($doc) {
                        // Extract description lines (skip @tags, method name echoes, blank lines)
                        // Match docblock content lines: " * Some text" but skip @tags
                        if (preg_match_all('/^\s*\*\s+([^@\/].*)$/m', $doc, $matches)) {
                            foreach ($matches[1] as $line) {
                                $line = trim($line, " \t*");
                                // Skip empty, method name echoes, or purely structural lines
                                if ($line === '' || strcasecmp($line, 'mainAction') === 0) {
                                    continue;
                                }
                                if (preg_match('/Action$/i', $line)) {
                                    continue;
                                }
                                return $line;
                            }
                        }
                    }
                }
                catch (Throwable) {
                }
                return '';
            }
        };

        return $command;
    }

    /**
     * Inject shared services into a TaskMaster instance.
     */
    public function injectServices(TaskMaster $task): void
    {
        $task->setService('basePath', $this->basePath);
        $task->setService('options', $this->options);
        $task->setService('possibleOptions', $this->possibleOptions);
        $task->setService('inputParams', $this->options); // backward compat alias
        $task->setService('logger', $this->createLogger());
    }

    /**
     * Get the option collection (for command configure).
     */
    public function getPossibleOptions(): OptionCollection
    {
        return $this->possibleOptions;
    }

    private function createLogger(): LoggerFactory
    {
        static $logger;
        if (!isset($logger)) {
            $appName = $GLOBALS['appName'] ?? 'doodle';
            $logger  = new LoggerFactory($this->basePath . '/' . $appName . '.log');
        }
        return $logger;
    }
}
