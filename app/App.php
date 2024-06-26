<?php

namespace Application;

use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;
use Library\StdIo;

final class App
{
	//	These directories will be skipped, including some extras for now.
	private static array $skipDirectories = ['app', 'lib', 'tests', 'vendor', 'Forensics', 'MusicPDF'];
	private const APP_OPTIONS = '/app/app_options.php';

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
		self::addOptions(include self::$appRoot . self::APP_OPTIONS);

		//  Add options from each working file or directory at the top level.
		//  Each file must have the variable $options that is an array of associative arrays.
		//  Directories must have at least one file named Main.php.
		$dirIter = new \DirectoryIterator(self::$appRoot);
		foreach ($dirIter as $itm) {
			$basename = $itm->getBasename('.php');
			$bnLower = strtolower($basename);

			if (substr($basename, 0, 1) === '.' || in_array($basename, self::$skipDirectories)) {
				continue;
			}

			$options = [];

			switch (true) {
				case ($itm->isFile() && $itm->getExtension() === 'php'):
					self::$commands[$bnLower] = $basename;

					include $itm->getPathname();

					if (
						class_exists($basename) &&
						$basename instanceof Command &&
						!empty($basename::$options)
					) {
						self::addOptions($basename::$options);
					}
					break;

				case $itm->isDir():
					$subIter = new \DirectoryIterator($itm->getPathname());
					self::$commands[$bnLower] = [];

					foreach ($subIter as $subItm) {
						if ($subItm->isFile() && $subItm->getExtension() === 'php') {
							$subBasename = $subItm->getBasename('.php');
							$fullClassName = $basename . '\\' . $subBasename;

							self::$commands[$bnLower][strtolower($subBasename)] = $fullClassName;

							include $subItm->getPathname();

							if (
								class_exists($fullClassName) &&
								$fullClassName instanceof Command &&
								!empty($fullClassName::$options)
							) {
								self::addOptions($fullClassName::$options);
							}
						}
					}
					break;

				default:
			}
		}

		self::$parser = new OptionParser(self::$specs);
	}

	/**
	 * Set an option based on the provided indexed array of associative arrays.
	 * Each associative array contains the keys: spec, desc, type, default, and inc.
	 * The only required key is spec.
	 *
	 * @param array $opts The array containing the keys: spec, desc, type, default, and inc.
	 */
	protected static function addOptions(array $opts)
	{
		foreach ($opts as $opt) {
			//	The following statement will cause an exception if the key is missing or invalid.
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

			if (isset($opt['inc']) && $opt['inc'] === true) {
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

			if (isset(self::$opts->help) || self::$opts->arguments === []) {
				fprintf(STDOUT, App . php(new ConsoleOptionPrinter())->render(self::$specs) . PHP_EOL);

				if (count(self::$opts->arguments) > 0) {
					//	get help from reflection
				}

				return 0;
			}
return 0;
			//	the first arg is the command name
			//  and corrisponds to a working directory
			//  and namespacewith a file/class named 'Main.php'

			//	the second arg can be another class/file
			//	in the same working directory or namespace

			$cmdObj = self::$commands[self::$opts->arguments[0]->arg];
			$cmdObj::init(self::$opts);
			$exitCode = $cmdObj::main();
		}
		catch (InvalidOptionException $e) {
			StdIo::err('Invalid option.');
			$exitCode = 1;
		}
		catch (InvalidOptionValueException $e) {
			echo 'Invalid value.' . PHP_EOL;
			$exitCode = 1;
		}
		catch (NonNumericException $e) {
			echo 'Option parameter must be numeric.' . PHP_EOL;
			$exitCode = 1;
		}
		catch (OptionConflictException $e) {
			echo 'Option conflict.' . PHP_EOL;
			$exitCode = 1;
		}
		catch (RequireValueException $e) {
			echo 'Option requires a value.' . PHP_EOL;
			$exitCode = 1;
		}

		return $exitCode;
	}

}
