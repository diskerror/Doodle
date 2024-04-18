<?php

namespace Resource;

use ArrayIterator;
use IteratorAggregate;
use Resource\Exception\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Structure\StringArray;

class Paths implements IteratorAggregate
{
	private $_path  = '';
	private $_files = [];

	private $_pathsToIgnore;

	public function __construct(StringArray $paths)
	{
		$this->_pathsToIgnore = $paths->toArray();
	}

	public function loadFileNames(string $path): void
	{
		$this->_path = realpath($path);

		if (!file_exists($this->_path)) {
			throw new InvalidArgumentException('The path "' . $this->_path . '" does not exist.');
		}

		$pathLen = strlen($this->_path) - strlen(basename($this->_path));

		$fileItr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->_path));

		$this->_files = [];
		foreach ($fileItr as $file) {
			$name = (string) $file->getPathname();
			if (!$file->isDir() && !$this->_arrayValueInString($name)) {
				$this->_files[substr($name, $pathLen)] = basename($name);
			}
		}
	}

	private function _arrayValueInString(string $haystack): bool
	{
		foreach ($this->_pathsToIgnore as $needle) {
			if (strpos($haystack, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	public function getIterator()
	{
		return new ArrayIterator($this->_files);
	}

}
