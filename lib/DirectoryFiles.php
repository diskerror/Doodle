<?php

namespace Library;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DirectoryFiles
{
	protected $_path;
	public    $files;

	protected $_pathsToIgnore = [];

	public function __construct(string $path, array $pathsToIgnore = [])
	{
		$this->_path = realpath($path);

		if (!file_exists($this->_path)) {
			throw new InvalidArgumentException('The path "' . $this->_path . '" does not exist.');
		}

		$this->setPathsToIgnore($pathsToIgnore);
		$this->loadFileNames();
	}

	public function setPathsToIgnore(array $pathsToIgnore = [])
	{
		$this->_pathsToIgnore = $pathsToIgnore;
	}

	public function loadFileNames()
	{
		$pathLen = strlen($this->_path);
		$pathLen -= strlen(basename($this->_path));

		$fileItr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->_path));

		$this->files = [];
		foreach ($fileItr as $file) {
			$name = (string) $file->getPathname();
			if (!$file->isDir() && !$this->_arrayValueInString($name)) {
				$this->files[substr($name, $pathLen)] = basename($name);
			}
		}
	}

	protected function _arrayValueInString(string $haystack)
	{
		foreach ($this->_pathsToIgnore as $needle) {
			if (strpos($haystack, $needle) !== false) {
				return true;
			}
		}
		return false;
	}
}
