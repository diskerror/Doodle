<?php

namespace Application;

use Application\Exception\RuntimeException;
use Application\Structure\Config;
use ErrorException;
use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;
use Library\StdIo;
use Phalcon\Cli\Console;
use Phalcon\Cli\Dispatcher\Exception as DispatcherException;
use Phalcon\Di\FactoryDefault\Cli as FdCli;
use Phalcon\Events\Manager;

final class App
{
    private FdCli            $di;
    private OptionCollection $specs;
    private OptionResult     $inputParams;

    public function __construct(string $basePath)
    {
        ini_set('error_reporting', E_ALL);
        set_error_handler(function ($errno, $message, $fname, $line) {
            throw new ErrorException($message, $errno, E_ERROR, $fname, $line);
        });

        setlocale(LC_CTYPE, 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');

        $this->specs = new OptionCollection();

        if (!is_dir($basePath)) {
            throw new RuntimeException('"' . $basePath . '" base path does not exist.');
        }

        $this->di = $di = new FdCli();
        $self     = $this;

        //	Setup shared resources and services.
        $this->di->setShared('basePath', function () use ($basePath) {
            return $basePath;
        });

        $this->di->setShared('config', function () use ($basePath) {
            static $config;

            if (!isset($config)) {
                //	File must exist and be in this directory.
                $config = new Config(require 'config.php');

                if (isset($GLOBALS['config'])) {
                    $config->replace($GLOBALS['config']);
                }
            }

            return $config;
        });

        //	This one will look like a property and act like a method.
        $this->di->setShared('showOptions', function () use ($self) {
            $self->showOptions();
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


        $appOptionsFile = $basePath . $this->di->getShared('config')->appOptionsFile;
        if (file_exists($appOptionsFile)) {
            self::addOptions(include $appOptionsFile);
        }

        /**
         * Allow additional options to be set from global variable.
         */
        if (array_key_exists('options', $GLOBALS)) {
            self::addOptions($GLOBALS['options']);
        }
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

            $this->specs->addOption($option);
        }
    }

    public function showOptions(): void
    {
        StdIo::outln();
        StdIo::outln((new ConsoleOptionPrinter())->render($this->specs));
    }

    public function parseArgv(array $argv): OptionResult
    {
        if (!isset($this->inputParams)) {
            $this->inputParams = (new OptionParser($this->specs))->parse($argv);

            $inputParams = $this->inputParams;
            $this->di->setShared('inputParams', function () use ($inputParams) {
                return $inputParams;
            });
        }

        return $this->inputParams;
    }

    public function run(array $argv): void
    {
        $ns = ucwords(basename($argv[0], '.php'), '.,-_+');

        if ($ns !== 'Doodle') {
            $this->di->get('dispatcher')->setDefaultNamespace($ns);
            $this->di->get('dispatcher')->setNamespaceName($ns);
        }

        $this->parseArgv($argv);

        $parsedArgv = [];
        foreach ($this->inputParams->arguments as $argument) {
            $parsedArgv[] = $argument->arg;
        }

        $application = new Console($this->di);

        if ($ns === 'Application') {
            //  Handle Doodle help uniquely.
            if ($this->inputParams->help) {
                $application->handle([
                                         'task' => 'main',
                                         'action' => 'help',
                                         'params' => [],
                                     ]);
                return;
            }
        }

        //  Handle help uniquely.
        if ($this->inputParams->help) {
            $application->handle([
                                     'task' => 'main',
                                     'action' => 'help',
                                     'params' => [],
                                 ]);
            return;
        }


        try {
            $workingArgv = $parsedArgv;
            $application->handle([
                                     'task' => count($workingArgv) ? array_shift($workingArgv) : 'main',
                                     'action' => count($workingArgv) ? array_shift($workingArgv) : 'main',
                                     'params' => $workingArgv,
                                 ]);
        }
        catch (DispatcherException) {
            try {
                $workingArgv = $parsedArgv;
                $application->handle([
                                         'task' => count($workingArgv) ? array_shift($workingArgv) : 'main',
                                         'action' => 'main',
                                         'params' => $workingArgv,
                                     ]);
            }
            catch (DispatcherException) {
                try {
                    $application->handle([
                                             'task' => 'main',
                                             'action' => 'main',
                                             'params' => $parsedArgv,
                                         ]);
                }
                catch (DispatcherException $de) {
                    StdIo::err($de->getMessage());
                    exit($de->getCode());
                }
            }
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
