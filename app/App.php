<?php

namespace Application;

use Application\Exceptions\BadVerbException;
use Application\Exceptions\MissingVerbException;
use DirectoryIterator;
use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;
use Library\StdIo;

final class App
{
	//	These directories will be skipped.
	private array $skipFiles = ['app', 'lib', 'tests', 'vendor'];
	private const APP_OPTIONS = '/app/options.php';
	private const APP_CACHE   = '/commandCache.phps';

	private string $rootDir;
	private array  $commands = [];

	private OptionCollection $specs;
	private OptionParser     $parser;
	private OptionResult     $inputParams;

	public function __construct(string $rootDir)
	{
		$this->rootDir = $rootDir;
		$this->specs   = new OptionCollection();

		// All inputParams will be defined in this file for now.
		if (file_exists($this->rootDir . self::APP_OPTIONS)) {
			self::addOptions(include $this->rootDir . self::APP_OPTIONS);
		}

		if (file_exists($this->rootDir . self::APP_CACHE)) {
			$this->commands = unserialize(file_get_contents($this->rootDir . self::APP_CACHE));
		}
		else {
			$appRootFiles = new DirectoryIterator($this->rootDir);
			foreach ($appRootFiles as $itm) {
				$basename = $itm->getBasename('.php');

				if (str_starts_with($basename, '.') || in_array($basename, $this->skipFiles)) {
					continue;
				}

				$bnLower = strtolower($basename);

				switch (true) {
					case ($itm->isFile() && ($itm->getExtension() === 'php')):
						$contents = file_get_contents($itm->getPathname());
						if (preg_match("/class\\s+$basename\\s+extends\\s+Command/", $contents)) {
							$this->commands[$bnLower]            = new CommandRef();
							$this->commands[$bnLower]->filePath  = $itm->getPathname();
							$this->commands[$bnLower]->className = $basename;
						}
						break;

					case $itm->isDir():
						$subIter = new DirectoryIterator($itm->getPathname() . '/');

						foreach ($subIter as $subItm) {
							if ($subItm->isFile() && ($subItm->getExtension() === 'php')) {
								$subBasename = $subItm->getBasename('.php');
								$sbnLower    = strtolower($subBasename);

								$contents = file_get_contents($subItm->getPathname());
								if (preg_match("/class.+$subBasename\\s+extends\\s+Command/", $contents)) {
									$this->commands[$bnLower][$sbnLower]            = new CommandRef();
									$this->commands[$bnLower][$sbnLower]->filePath  = $itm->getPathname();
									$this->commands[$bnLower][$sbnLower]->dirName   = $basename;
									$this->commands[$bnLower][$sbnLower]->className = $subBasename;
								}
							}
						}
						break;

					default:
				}
			}

			file_put_contents($this->rootDir . self::APP_CACHE, serialize($this->commands));
		}

		$this->parser = new OptionParser($this->specs);
	}

	/**
	 * Set an option based on the provided indexed array of associative arrays.
	 * Each associative array contains the keys: spec, desc, type, default, and inc.
	 * The only required key is spec.
	 *
	 * @param array $optsIn The indexed array containing the keys: spec, desc, isa, defaultValue, and incremental.
	 */
	protected function addOptions(array $optsIn): void
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

	protected function printHelp(): void
	{
		StdIo::outln('Possible commands:');
		foreach ($this->commands as $p_command => $commandData) {
			if ($commandData instanceof CommandRef) {
				StdIo::out('        ');
				StdIo::outln($p_command);
				StdIo::outln();
			}
			else {
				foreach ($commandData as $subCommandName => $subCommandData) {
					StdIo::out('        ');
					StdIo::outln($p_command . ' ' . $subCommandName);
					StdIo::outln();
				}
			}
		}
		StdIo::outln();
		StdIo::outln((new ConsoleOptionPrinter())->render($this->specs));
	}

	public function run(array $argv): int
	{
		$exit_code = 0;

		try {
			$this->inputParams = $this->parser->parse($argv);

			$argument1 = $this->inputParams->arguments !== [] ?
				array_shift($this->inputParams->arguments)->arg :
				'';

			//  If no arguments or -h then display help and exit
			if (
				isset($this->inputParams->help) ||
				$argument1 === '' ||
				$argument1 === 'help'
			) {
				$this->printHelp();
				return 0;
			}

			if (!array_key_exists($argument1, $this->commands)) {
				unlink($this->rootDir . self::APP_CACHE);
				throw new BadVerbException();
			}

			//	Argument 1 is in array at this point.
			$thisCommand = $this->commands[$argument1];
			$argument2   = $this->inputParams->arguments !== [] ? $this->inputParams->arguments[0]->arg : '';

			$runner = null;
			$method = '';

			if ($thisCommand instanceof CommandRef) {
				//	This must be a file.
				//  Check if second argument matches a public method,
				//      remove from argument list, then call it.
				if (method_exists($thisCommand->className, $argument2)) {
					array_shift($this->inputParams->arguments);
					$exit_code = (new $thisCommand->className($this->inputParams))->$argument2();
				}
				else {
					$exit_code = (new $thisCommand->className($this->inputParams))->main();
				}
			}
			else {
				//	Else $cmd contains an array of CommandRef's
				//  Argument 2 is required here.
				if ($argument2 === '') {
					throw new MissingVerbException();
				}
				array_shift($this->inputParams->arguments);

				$thisSubCommand = $thisCommand[$argument2];
				$fullClassName  = $thisSubCommand->dirName . '\\' . $thisSubCommand->className;

				//	Check if third argument matches a public method, call it.
				$argument3 = $this->inputParams->arguments !== [] ? $this->inputParams->arguments[0]->arg : '';
				if (method_exists($fullClassName, $argument3)) {
					$exit_code = (new $fullClassName($this->inputParams))->$argument3();
				}
				else {
					$exit_code = (new $fullClassName($this->inputParams))->main();
				}
			}
		}
		catch (InvalidOptionException $e) {
			StdIo::err('Invalid option.');
			$exit_code = 1;
		}
		catch (InvalidOptionValueException $e) {
			StdIo::err('Invalid option value.');
			$exit_code = 1;
		}
		catch (NonNumericException $e) {
			StdIo::err('Option parameter must be numeric.');
			$exit_code = 1;
		}
		catch (OptionConflictException $e) {
			StdIo::err('Option conflict.');
			$exit_code = 1;
		}
		catch (RequireValueException $e) {
			StdIo::err('Option requires a value.');
			$exit_code = 1;
		}
		catch (MissingVerbException $e) {
			StdIo::err('Missing command verb.');
			$exit_code = 1;
		}
		catch (BadVerbException $e) {
			StdIo::err('Bad command verb.');
			$exit_code = 1;
		}

		return $exit_code;
	}

}
