<?php

namespace Music;

use Application\TaskMaster;
use Diskerror\Typed\DateTime;
use ErrorException;
use Library\StdIo;


class TocTask extends TaskMaster
{
    /**
     * Adds Table Of Contents to a PDF file.
     *
     * Format of TOC source file:
     * <tab>1<tab>Page One Level One
     * <tab><tab>5<tab>Page Five Level Two
     * <tab><tab>7<tab>Page Seven Level Two
     * <tab><tab><tab>8<tab>Page Eight Level Three
     *
     * Numbering start with physical page one (1).
     *
     * @return int
     * @throws ErrorException
     */
    public function mainAction(...$args)
    {
        $this->logger->info('Music TocTask mainAction');

        if (count($args) != 1) {
            $this->helpAction();
            return;
        }
        $startTime = new DateTime();

        $tocData = '';
        $toc     = explode(PHP_EOL, file_get_contents($this->options->toc));
        foreach ($toc as $entry) {
            try {
                $parts = [];
                if (!preg_match('/^(\\t+)(\d+)\\t(.+)$/', $entry, $parts)) {
                    continue;
                }
                $tocData .= "BookmarkBegin\n";
                $tocData .= 'BookmarkTitle: ' . $parts[3] . "\n";
                $tocData .= 'BookmarkLevel: ' . strlen($parts[1]) . "\n";
                $tocData .= 'BookmarkPageNumber: ' . $parts[2] . "\n";
            }
            catch (ErrorException) {
            }
        }

        $pdfInName        = \Library\escapeshellarg($args[0]);
        $pathinfo         = pathinfo($args[0]);
        $infoTempFileName = $pathinfo['dirname'] . '/' . $pathinfo['basename'] . '_TMP.txt';

        $dataDump = explode("\n", shell_exec("pdftk $pdfInName dump_data_utf8 output -"));

        $newFile = fopen($infoTempFileName, 'wb');
        foreach ($dataDump as $line) {
            if ($line === '') {
                continue;
            }

            fwrite($newFile, $line . "\n");
            //  Put TOC after "NumberOfPages" line
            if (str_starts_with($line, 'NumberOfPages:')) {
                fwrite($newFile, $tocData);
            }
        }
        fflush($newFile);
        fclose($newFile);


        $infoTempFileEsc = \Library\escapeshellarg($infoTempFileName);
        $pdfOutName      =
            \Library\escapeshellarg($pathinfo['dirname'] . '/' . substr($pathinfo['basename'], 0, -4) . '_NEW.pdf');

        exec("pdftk $pdfInName update_info_utf8 $infoTempFileEsc output $pdfOutName");
        unlink($infoTempFileName);


        StdIo::outln('Total runtime: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));
    }

}
