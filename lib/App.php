<?php

namespace Library;

use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;

final class App
{
	private static array $skipDirectories = ['vendor', 'tests', 'lib'];
	private const APP_OPTIONS = '/lib/app_options.php';

	private static string $appRoot;
	private static array $commands = [];

	private static OptionCollection $specs;
	private static OptionParser $parser;
	private static OptionResult $opts;

	public static function init(string $appRoot): void
	{
		self::$appRoot = $appRoot;
		self::$specs = new OptionCollection();

		//  The global_options.php file must return an indexed array of associative arrays.
		self::setOptions(include $appRoot . self::APP_OPTIONS);

		//  Add options from each working file or directory at the top level.
		//  Each file must have the variable $options that is an array of associative arrays.
		//  Directories must have at least one file named Main.php.
		$dirIter = new \DirectoryIterator($appRoot);
		foreach ($dirIter as $itm) {
			$basename = $itm->getBasename('.php');

			if (substr($basename, 0, 1) !== '.' && !in_array($basename, self::$skipDirectories)) {
				$options = [];

				if ($itm->isDir()) {
					$mainClass = $basename . '\\Main';
					self::$commands[strtolower($basename)] = $mainClass;
					include $itm->getPathname() . '/Main.php';
					$options = $mainClass::$options;
				} elseif ($itm->isFile() && $itm->getExtension() === 'php') {
					self::$commands[strtolower($basename)] = $basename;
					include $itm->getPathname();
					$options = $basename::$options;
				}

				if (!empty($options)) {
					self::setOptions($options);
					unset($options);
				}
			}
		}

		self::$parser = new OptionParser(self::$specs);
	}

	/**
	 * Set an option based on the provided indexed array of associative arrays.
	 *
	 * @param array $opts The array containing the keys: spec, desc, type, default, and inc.
	 */
	protected static function setOptions(array $opts)
	{
		foreach ($opts as $opt) {
			$option = new Option($opt['spec']);

			if (isset($opt['desc'])) {
				$option->desc = $opt['desc'];
			}

			if (isset($opt['type'])) {
				$option->isa = $opt['type'];
			}

			if (isset($opt['default'])) {
				$option->defaultValue = $opt['default'];
			}

			if (isset($opt['incremental']) && $opt['incremental'] === true) {
				$option->incremental = true;
			}

			self::$specs->addOption($option);
		}
	}

	public static function run(array $argv): int
	{
		try {
			self::$opts = self::$parser->parse($argv);
			if (!isset(self::$opts->verbose)) {
				self::$opts->verbose = 0;
			}

			//  If no arguments then display help and exit
			//  If -h then display help and exit

			if (isset(self::$opts->help)) {
				fprintf(STDOUT, (new ConsoleOptionPrinter())->render(self::$specs) . PHP_EOL);

				if (count(self::$opts->arguments) > 0) {
					//	get help from reflection
				}

				return 0;
			}

			//	the first arg is the command name
			//  and corrisponds to a working directory
			//  and namespacewith a file/class named 'Main.php'

			//	the second arg can be another class/file
			//	in the same working directory or namespace

			$cmdObj = self::$commands[self::$opts->arguments[0]->arg];
			$cmdObj::init(self::$opts);
			$exitCode = $cmdObj::main();

		}
		catch
		(InvalidOptionException $e) {
			echo 'Invalid option.' . PHP_EOL;
			return 1;
		}
		catch (InvalidOptionValueException $e) {
			echo 'Invalid value.' . PHP_EOL;
			return 1;
		}
		catch (NonNumericException $e) {
			echo 'Option parameter must be numeric.' . PHP_EOL;
			return 1;
		}
		catch (OptionConflictException $e) {
			echo 'Option conflict.' . PHP_EOL;
			return 1;
		}
		catch (RequireValueException $e) {
			echo 'Option requires a value.' . PHP_EOL;
			return 1;
		}

		return $exitCode;
	}

}
