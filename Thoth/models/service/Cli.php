<?php

namespace Service;

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use Phalcon\Cli\Console;
use Phalcon\Cli\Dispatcher\Exception;
use Phalcon\Di\FactoryDefault\Cli as FdCli;
use Phalcon\Events\Manager;
use Resource\LoggerFactory;
use Resource\PidHandler;
use Service\Exception\RuntimeException;
use Structure\Config;
use function substr;

class Cli
{
	/**
	 * @var string
	 */
	protected $_basePath;

	/**
	 * @var (application)
	 */
	protected $_application;

	/**
	 * DiAbstract constructor.
	 *
	 * @param string $basePath
	 */
	final public function __construct(string $basePath)
	{
		if (!is_dir($basePath)) {
			throw new RuntimeException('"$_basePath" base path does not exist.');
		}

		$this->_basePath = $basePath;
	}

	/**
	 * @param array $argv
	 *
	 * @throws Exception
	 */
	public function run(array $argv): string
	{
		$di = new FdCli();

		$basePath = $this->_basePath;

		//	Setup shared resources and services.
		$di->setShared('basePath', function() use ($basePath) {
			return $basePath;
		});

		$di->setShared('config', function() use ($basePath) {
			static $config;

			if (!isset($config)) {
				$configName = $basePath . '/config.php';
				$devName    = $basePath . '/dev.config.php';

				//	File must exist.
				$config = new Config(require $configName);

				if (file_exists($devName)) {
					$config->replace(require $devName);
				}

				$config = new Config($config);

				$userConfigName = getenv('HOME') . '/' . $config->userConfigName;
				if (file_exists($userConfigName)) {
					$config->replace(require $userConfigName);
				}
			}

			return $config;
		});

		$di->setShared('logger', function() use ($basePath, $di) {
			static $logger;
			if (!isset($logger)) {
				$logger = new LoggerFactory(
					$basePath . '/' . $di->getShared('config')->process->name . '.log'
				);
			}
			return $logger;
		});

		$di->setShared('eventsManager', function() {
			static $eventsManager;
			if (!isset($eventsManager)) {
				$eventsManager = new Manager();
			}
			return $eventsManager;
		});

		$di->setShared('pidHandler', function() use ($di) {
			static $pidHandler;
			if (!isset($pidHandler)) {
				$pidHandler = new PidHandler($di->getShared('config')->process);
			}
			return $pidHandler;
		});

		//	Parse command line options.
		$opts = new OptionCollection();
		$opts->add('v|verbose');
		$parser     = new OptionParser($opts);
		$result     = $parser->parse($argv);
		$parsedArgv = [];
		foreach ($result->arguments as $argument) {
			$parsedArgv[] = $argument->arg;
		}

		//	Reassemble command line arguments.
		$args           = [];
		$args['task']   = (count($parsedArgv)) ? array_shift($parsedArgv) : '';
		$args['action'] = (count($parsedArgv)) ? array_shift($parsedArgv) : '';
		$args['params'] = $parsedArgv;

		try {
			$application = new Console($di);
			$application->handle($args);
		}
		catch (\Exception $e) {
			$message = $e->getMessage();
			if (($pos = strpos($message, 'Task handler class cannot be loaded')) !== false) {
				StdIo::err(substr($message, 0, $pos) . ' command does not exist.');
			}
			else {
				throw $e;
			}
		}

		return PHP_EOL;
	}
}
