<?php

use Diskerror\Typed\TypedArray;

class PdfMetaDataList extends TypedArray
{
	protected string $_type = PdfMetaData::class;

	public function loadFile(string $fileName)
	{
		$fp = fopen($fileName, 'rb');

		$head = explode("\t", fgets($fp));

		while (!feof($fp)) {
			$row = explode("\t", fgets($fp));
			parent::offsetSet($row[0], $row);
		}

		fclose($fp);

	}

	public function loadDB()
	{
		$db = new SQLite3(__DIR__ . '/music.sqlite');
		$res = $db->query('SELECT * FROM meta');
		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			parent::offsetSet($row['filename'], $row);
		}
		$db->close();
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new Exception('can\'t set value here');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new Exception('can\'t unset value here');
	}
}
