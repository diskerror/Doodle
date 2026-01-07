<?php

namespace Music;

use Application\TaskMaster;
use DateTime;
use DateTimeZone;
use ErrorException;
use Library\Exceptions\BadFileException;
use Library\Exceptions\RuntimeException;
use Library\SQLite3;
use Library\StdIo;
use Shuchkin\SimpleXLSX;
use SplFileObject;
use function Library\escapeshellarg;


class MetaDataTask extends TaskMaster
{
	const string MUSIC_DB        = __DIR__ . '/music.sqlite';
	const string EXCEL_META_DATA = '/Music/Music Meta Data.xlsx';

	const KEY_XLATE = [
		'Cb' => [-7, 0],
		'Gb' => [-6, 0],
		'Db' => [-5, 0],
		'Ab' => [-4, 0],
		'Eb' => [-3, 0],
		'Bb' => [-2, 0],
		'F' => [-1, 0],
		'C' => [0, 0],
		'G' => [1, 0],
		'D' => [2, 0],
		'A' => [3, 0],
		'E' => [4, 0],
		'B' => [5, 0],
		'F#' => [6, 0],
		'C#' => [7, 0],
		'ab' => [-7, 1],
		'eb' => [-6, 1],
		'bb' => [-5, 1],
		'f' => [-4, 1],
		'c' => [-3, 1],
		'g' => [-2, 1],
		'd' => [1, 1],
		'a' => [0, 1],
		'e' => [1, 1],
		'b' => [2, 1],
		'f#' => [3, 1],
		'c#' => [4, 1],
		'g#' => [5, 1],
		'd#' => [4, 1],
		'a#' => [7, 1],
	];

	/**
	 * mainAction
	 *
	 * Applies meta-data to PDF file by file name.
	 *
	 * @return void
	 * @throws ErrorException
	 */
	public function mainAction(...$files): void {
		if (count($files) === 0) {
			$this->helpAction();
			return;
		}

		// load Excel file if newer than local DB
		$real_excel = realpath(getenv('HOME') . self::EXCEL_META_DATA);
		if (file_exists($real_excel) && filemtime($real_excel) > filemtime(self::MUSIC_DB)) {
			$this->doSetDb();
			$this->loadExcelAction($real_excel);
		}

		$db = new SQLite3(self::MUSIC_DB);
		$db->enableExceptions(true);

		foreach ($files as $file) {

			if (!is_file($file)) {
				throw new BadFileException('Not a file.' . PHP_EOL . '  ' . $file . PHP_EOL);
			}

			$output      = '';
			$result_code = 0;
			$bname       = basename($file);

			$pmd = new PdfMetaData(
				$db->querySingle("SELECT title, author, subject, keywords FROM meta WHERE filename = '$bname'", true)
			);

			if ($pmd->title !== '') {
				$file     = escapeshellarg($file);
				$title    = escapeshellarg($pmd->title);
				$author   = escapeshellarg($pmd->author);
				$subject  = escapeshellarg($pmd->subject);
				$keywords = escapeshellarg($pmd->keywords);

				$cmd = 'exiftool -overwrite_original -Creator="Reid Woodbury Jr." ' .
					"-Title=$title -Author=$author -Subject=$subject -Keywords=$keywords $file";

//                StdIo::outln($cmd);
//                continue;
				exec($cmd, $output, $result_code);

				if ($result_code) {
					throw new RuntimeException('ERROR: ' . $cmd . PHP_EOL . $output . PHP_EOL . $result_code);
				} else {
					StdIo::outln($bname . ' - done');
				}
			} else {
				StdIo::outln('File has no meta data.');
				StdIo::outln('  ' . $file);
			}
		}
	}

	/**
	 * setDbAction
	 *
	 * Initializes the database.
	 * WARNING: This will delete the and existing database!
	 *
	 * @return void
	 */
	public function setDbAction() {
		StdIo::outln('WARNING: This will delete the and existing database!');
		StdIo::out('Continue? (y/n)');
		$response = StdIo::in();
		if (trim($response) !== 'y') {
			return;
		}

		$this->doSetDb();
		StdIo::outln('Database initialized.');
	}

	protected function doSetDb() {
		$db = new SQLite3(self::MUSIC_DB);
		$db->exec('DROP TABLE if EXISTS meta');
		$db->exec('
CREATE TABLE meta (
    meta_id INTEGER PRIMARY KEY,
    filename text,
    title text,
    author text,
    subject text,
    keywords text)
');
		$db->exec('CREATE UNIQUE INDEX idx_filename ON meta (filename)');
		$db->exec('CREATE INDEX idx_title ON meta (title)');
		$db->exec('CREATE INDEX idx_author ON meta (author)');
		$db->exec('CREATE INDEX idx_subject ON meta (subject)');
		$db->exec('CREATE INDEX idx_keywords ON meta (keywords)');
	}

	/**
	 * exportAction
	 *
	 * Exports the database to a CSV file.
	 * If the file name ends in .tsv, the separator will be a tab.
	 * Otherwise, the separator will be a comma.
	 *
	 * @param ...$args
	 * @return void
	 */
	public function exportAction(...$args) {
		if (count($args) !== 1) {
			StdIo::outln('needs output file name');
			$this->helpAction();
			return;
		}

		$fo        = new SplFileObject($args[0], 'wb');
		$separator = '';
		switch (strtolower($fo->getExtension())) {
			case 'tsv':
				$separator = "\t";
			break;

			case 'csv':
				$separator = ',';
			break;

			default:
				throw new RuntimeException('Unknown file extension: ' . $fo->getExtension());
		}

		$db  = new SQLite3(self::MUSIC_DB);
		$res = $db->query('SELECT * FROM meta ORDER BY meta_id');

		$row = $res->fetchArray(SQLITE3_ASSOC);
		$fo->fputcsv(array_keys($row), $separator);
		$fo->fputcsv($row, $separator);

		while ($row = $res->fetchArray(SQLITE3_NUM)) {
			$fo->fputcsv($row, $separator);
		}
	}

	/**
	 * loadExcelAction
	 * Loads an Excel file into the database.
	 * @param ...$args
	 * @return void
	 * @throws \JsonException
	 */
	public function loadExcelAction(...$args) {
		if (count($args) !== 1) {
			StdIo::outln('needs file (only one)');
			$this->helpAction();
			return;
		}

		if (pathinfo($args[0], PATHINFO_EXTENSION) !== 'xlsx') {
			StdIo::outln('needs xlsx file');
			$this->helpAction();
			return;
		}

		StdIo::outln('loading Excel file');
		$xlsx = SimpleXLSX::parsefile($args[0]);
		if (!$xlsx) {
			throw new RuntimeException('error parsing Excel file');
		}

		$data   = $xlsx->rows();
		$header = array_shift($data);

		$data = array_map(function ($row) use ($header) {
			return array_combine($header, $row);
		}, $data);

		$db = new SQLite3(self::MUSIC_DB);
		$db->exec('PRAGMA journal_mode = wal;');
		$stmt = $db->prepare('INSERT INTO meta (filename, title, author, subject, keywords) ' .
			'VALUES (:filename, :title, :author, :subject, :keywords)');

		foreach ($data as $row) {
			$newRow = new PdfMetaData($row);

			foreach ($row as $key => $value) {
				$value = (string)$value;
				$value = preg_replace('/\s+/', ' ', trim($value));

				if ($value === '') {
					continue;
				}

				switch ($key) {
					case 'Start Page':
						$newRow->keywords = 'p:' . $value;
					break;

					case 'Arranger':
						$value            = preg_replace('/\s+/', ' ', $value);
						$newRow->keywords = 'arranger:' . $value;
					break;

					case 'Text':
						$value            = preg_replace('/\s+/', ' ', $value);
						$newRow->keywords = 'text:' . $value;
					break;

					case 'Key':
						$newRow->keywords = 'keysf:' . self::KEY_XLATE[$value][0];
						$newRow->keywords = 'keymi:' . self::KEY_XLATE[$value][1];
					break;

					case 'Duration':
						$time             = new DateTime($value, new DateTimeZone('UTC'));
						$newRow->keywords = 'duration:' . (int)($time->getTimestamp() / 60);
					break;

					case 'Publisher':
						$newRow->keywords = 'publisher:' . $value;
					break;

					case 'Year':
						$newRow->keywords = 'year:' . (int)$value;
					break;

					case 'Plate':
						$newRow->keywords = 'plate:' . $value;
					break;

					case 'Rating':
						$newRow->keywords = 'rating:' . (int)$value;
					break;

					case 'Difficulty':
						$newRow->keywords = 'difficulty:' . (int)$value;
					break;
				}
			}

			$stmt->bindValue(':filename', $newRow->filename);
			$stmt->bindValue(':title', $newRow->title);
			$stmt->bindValue(':author', $newRow->author);
			$stmt->bindValue(':subject', $newRow->subject);
			$stmt->bindValue(':keywords', $newRow->keywords);
			$stmt->execute();
		}
	}

}
