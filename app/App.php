<?php

namespace Application;

use Application\Structure\Config;
use ErrorException;
use Exception;
use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionResult;
use Library\StdIo;
use Phalcon\Cli\Dispatcher\Exception as DispatcherException;
use Phalcon\Di\FactoryDefault\Cli as FdCli;
use Phalcon\Events\Manager;
use Throwable;

final class App
{
    private Config           $config;
    private FdCli            $di;
    private OptionCollection $possibleOptions;
    private OptionResult     $inputParams;  //  deprecated
    private OptionResult     $options;

    //  Change error handling to throw exceptions.
    public static function _errorHandler($errno, $message, $fname, $line)
    {
        throw new ErrorException($message, $errno, E_ERROR, $fname, $line);
    }

    //  Catch uncaught exceptions.
    public static function _exceptionHandler(Throwable $t)
    {
        fprintf(STDERR, '%s' . PHP_EOL, $t->getMessage());
        if (isset($GLOBALS['doodle_debug']) && $GLOBALS['doodle_debug']) {
            fprintf(STDERR, '%s' . PHP_EOL, $t->getTraceAsString());
        }
        exit($t->getCode());
    }

    public static function _shutdownHandler()
    {
        $lastError = error_get_last();
        if ($lastError !== null && $lastError['type'] === E_ERROR) {
            fprintf(STDERR, 'Uncaught exception: %s' . PHP_EOL, $lastError['message']);
            fprintf(STDERR, '%s:%s' . PHP_EOL, $lastError['file'], $lastError['line']);
        }
    }

    public static function _signalHandler(int $signo, mixed $siginfo)
    {
        fprintf(STDERR, 'Received signal: %d' . PHP_EOL, $signo);
        fprintf(STDERR, '%s' . PHP_EOL, var_export($siginfo, true));

        $lastError = pcntl_get_last_error();
        if ($lastError !== null) {
            fprintf(STDERR, 'Received error %d with message: %s' . PHP_EOL, $lastError, pcntl_strerror($lastError));
        }

        exec('
  local PID
  for PID in $CHILD_PID; do
    if [[$(kill -0 $PID) < = /dev/null]]; then
      kill -SIGKILL $PID
    fi
  done
  pkill -P $$
');

        exit($signo);
    }

    /**
     * App constructor.
     * Set up the environment.
     * @throws \GetOptionKit\Exception\OptionConflictException
     * @throws \Phalcon\Di\Exception
     */
    public function __construct()
    {
        ini_set('error_reporting', E_ALL);
        set_error_handler([self::class, '_errorHandler']);
        set_exception_handler([self::class, '_exceptionHandler']);
        register_shutdown_function([self::class, '_shutdownHandler']);
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGQUIT, SIGTSTP] as $signal) {
            try {
                pcntl_signal($signal, [self::class, '_signalHandler']);
            }
            catch (Exception $e) {
                //  Ignore.
            }
        }
        pcntl_async_signals();


        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        $this->di = $di = new FdCli();
        $self     = $this;
        $basePath = realpath(__DIR__ . '/..'); //	Relative to this file, ~/Doodle/.

        //	Setup shared resources and services.
        $this->di->setShared('basePath', function () use ($basePath) {
            return $basePath;
        });


        //	File must exist and be in this directory.
        $this->config = new Config(require 'config.php');

        if (isset($GLOBALS['config'])) {
            $this->config->replace($GLOBALS['config']);
        }
        $this->di->setShared('config', function () use ($self) {
            return $self->config;
        });


        $this->possibleOptions = new OptionCollection();
        $appOptionsFile        = $basePath . $this->di->getShared('config')->appOptionsFile;

        if (file_exists($appOptionsFile)) {
            $this->addOptions(include $appOptionsFile);
        }

        //  Allow additional options to be set from global variable.
        if (array_key_exists('options', $GLOBALS)) {
            $this->addOptions($GLOBALS['options']);
        }

        $this->di->setShared('possibleOptions', function () use ($self) {
            return $self->possibleOptions;
        });


        $this->di->setShared('logger', function () use ($basePath, $di) {
            static $logger;
            if (!isset($logger)) {
                $logger = new LoggerFactory(
                    $basePath . '/' . $di->getShared('config')->process->name . '.log'
                );
            }
            return $logger;
        });

        $this->di->setShared('eventsManager', function () {
            static $eventsManager;
            if (!isset($eventsManager)) {
                $eventsManager = new Manager();
            }
            return $eventsManager;
        });

        $this->di->setShared('pidHandler', function () use ($di) {
            static $pidHandler;
            if (!isset($pidHandler)) {
                $pidHandler = new PidHandler($di->getShared('config')->process);
            }
            return $pidHandler;
        });
    }

    /**
     * Set an option based on the provided indexed array of associative arrays.
     * Each associative array contains the keys: spec, desc, type, default, and inc.
     * The only required key is spec.
     *
     * @param array $optsIn The indexed array containing the keys: spec, desc, isa, defaultValue, and incremental.
     */
    private function addOptions(array $optsIn): void
    {
        foreach ($optsIn as $opt) {
            $option = new Option($opt['spec']);
            unset($opt['spec']);

            foreach ($opt as $optKey => $optValue) {
                $option->$optKey = $optValue;
            }

            $this->possibleOptions->addOption($option);
        }
    }

    public function run(array $argv): void
    {
        $this->inputParams = (new OptionParser($this->possibleOptions))->parse($argv);

        $inputParams = $this->inputParams;
        $this->di->setShared('inputParams', function () use ($inputParams) {
            return $inputParams;
        });

        $GLOBALS['doodle_debug'] = (bool)$this->inputParams->debug;

        $parsedParams = $this->inputParams->getArguments();
        $paramCt      = count($parsedParams);

        //  Set new source of parsed options.
        unset($this->inputParams->arguments);
        $options = $this->inputParams;
        $this->di->setShared('options', function () use ($options) {
            return $options;
        });

        //  Initially, the namespace is based on the filename that starts the script.
        $activeNamespace = ucwords(basename($argv[0], '.php'), '.,-_+');

        //  'Doodle' by itself calls help for all projects (Application\MainTask::helpAction()).
        switch (true) {
            case $activeNamespace === 'Doodle' && $paramCt === 0:
            case $activeNamespace === 'Doodle' && $paramCt >= 1 && strtolower($parsedParams[0]) === 'help':
                $activeNamespace = 'Application';
                $parsedParams    = ['main', 'help'];
                break;

            case $activeNamespace === 'Doodle':
                $activeNamespace = ucwords(basename(array_shift($parsedParams), '.php'), '.,-_+');
                break;

            default:
        }

        $this->dispatcher->setDefaultNamespace($activeNamespace);
        $this->dispatcher->setNamespaceName($activeNamespace);

        //  This will choose help for the set namespace.
        if ($this->inputParams->help || (count($parsedParams) >= 1 && strtolower($parsedParams[0]) === 'help')
        ) {
            $parsedParams = ['main', 'help'];
        }

        try {
            (new Console($this->di))->handle($parsedParams);
        }
        catch (DispatcherException $de) {
            StdIo::err($de->getMessage());
            exit($de->getCode());
        }
    }


    public function __get(string $name)
    {
        switch ($name) {
            case 'inputParams':
                return $this->inputParams;

            default:
        }

        return $this->di->getShared($name);
    }

}
