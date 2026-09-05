<?php

namespace Music;

use Application\TaskMaster;
use ErrorException;
use Library\StdIo;


class TocTask extends TaskMaster
{
	protected static array $taskOptions = [
		['spec' => 't|toc:=file', 'desc' => 'File with new TOC.'],
	];

	/**
	 * mainAction
	 *
	 * Adds Table Of Contents to a PDF file.
	 *
	 * Format of TOC source file:
	 * 1<tab>Page One Level One
	 * 5<tab><tab>Page Five Level Two
	 * 7<tab><tab>Page Seven Level Two
	 * 8<tab><tab><tab>Page Eight Level Three
	 *
	 * Numbering start with physical page one (1).
	 *
	 * @return void
	 * @throws ErrorException
	 */
	public function mainAction(...$args): void
	{
		$this->logger->info('Music TocTask mainAction');

		if (count($args) != 1) {
			$this->helpAction();
			return;
		}

		$tocData = '';
		$toc     = explode(PHP_EOL, file_get_contents($this->options->toc));
		foreach ($toc as $entry) {
			try {
				$parts = [];
				if (!preg_match('/^(\d+)(\\t+)(.+)$/', $entry, $parts)) {
					continue;
				}
				$tocData .= "BookmarkBegin\n";
				$tocData .= 'BookmarkTitle: ' . $parts[3] . "\n";
				$tocData .= 'BookmarkLevel: ' . strlen($parts[2]) . "\n";
				$tocData .= 'BookmarkPageNumber: ' . $parts[1] . "\n";
			}
			catch (ErrorException) {
			}
		}

		$workingPdfName   = $args[0];
		$workingPathInfo  = pathinfo($args[0]);
		$infoTempFileName = $workingPathInfo['dirname'] . '/' . $workingPathInfo['basename'] . '_TMP.txt';

		$workingDataDump = shell_exec("pdftk '$workingPdfName' dump_data_utf8 output -");

		$infoTempFile = fopen($infoTempFileName, 'wb');
		foreach (explode("\n", $workingDataDump) as $line) {
			if ($line === '') {
				continue;
			}

			fwrite($infoTempFile, $line . "\n");
			//  Put TOC after "NumberOfPages" line
			if (str_starts_with($line, 'NumberOfPages:')) {
				fwrite($infoTempFile, $tocData);
			}
		}
		fflush($infoTempFile);
		fclose($infoTempFile);

		$backupFileName = $workingPathInfo['dirname'] . '/' . substr($workingPathInfo['basename'], 0, -4) . '_BU.pdf';
		rename($workingPdfName, $backupFileName);
		exec("pdftk '$backupFileName' update_info_utf8 '$infoTempFileName' output '$workingPdfName'");
		unlink($infoTempFileName);

		StdIo::outln('Done.');
	}

}
