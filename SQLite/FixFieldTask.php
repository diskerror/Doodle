<?php

namespace SQLite;

use Application\TaskMaster;
use Library\SQLite3;
use Library\StdIo;

class FixFieldTask extends TaskMaster
{
	/**
	 * Fix a column in a SQLite DB.
	 */
	public function mainAction(...$in): void
	{
		if (count($in) != 1) {
			StdIo::err('Need file name. One file.');
			return;
		}

		if (!file_exists($in[0])) {
			StdIo::err("File doesn't exist.");
			return;
		}

		$db = new SQLite3($in[0]);

		$rows    = $db->query('SELECT document_id, text FROM documents');
		$stmt    = $db->prepare('UPDATE documents SET text = :tx WHERE document_id = :id');

		$totalCt   = 0;
		$changedCt = 0;
		while ($row = $rows->fetchArray()) {
			$totalCt++;

			// Manual line-by-line stripping—avoids regex encoding bugs
			$lines = explode("\n", strtr($row['text'], ["\r\n" => "\n", "\r" => "\n"]));
			foreach ($lines as &$line) {
				$line = ltrim($line, ' #');  // Remove leading spaces and hashes
			}
			$newText = implode("\n", $lines);
			
			if ($newText !== $row['text']) {
				$stmt->bindValue(':tx', $newText, SQLITE3_TEXT);
				$stmt->bindValue(':id', $row['document_id'], SQLITE3_INTEGER);
				$stmt->execute();
				$changedCt++;
			}
		}

		StdIo::outln('Changed / Total:  ' . $changedCt . ' / ' . $totalCt);
	}

}