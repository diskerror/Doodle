<?php

namespace Music;

use Application\TaskMaster;
use DateTime;
use ErrorException;
use Library\ProcessRunner;

include 'lib/var_export.php';


class Tif2PdfTask extends TaskMaster
{
    /**
     * Converts all '.tif' files in a directory to a single '.pdf'.
     *
     * @return int
     * @throws ErrorException
     */
    public function mainAction()
    {
        $args = $this->inputParams->arguments;
        array_shift($args);

        if (count($args) != 1) {
            $this->helpAction();
            return;
        }

        $startTime = new DateTime();


        // Some ImageMagick functions do not like spaces in file names
        // so with this we can avoid spaces in the parent directories.
        // A bug report needs to be filed with ImageMagick.
        $workingDir = $args[0]->arg;
        chdir($workingDir);
        $fNames = glob('*.tif', GLOB_ERR);
        natsort($fNames); //  so numbers don't need leading zeros

        // Turn file names into ImageFileInfo objects.
        $tifFiles = [];
        foreach ($fNames as $fName) {
            $tifFiles[] = new ImageFileInfo($fName);
        }

        $averageWidth = 0;
        for ($f = 0; $f < count($tifFiles); $f++) {
            $averageWidth += $tifFiles[$f]->width;  //  widest image
        }
        $averageWidth = round($averageWidth / count($tifFiles));
        $averageWidth = min($averageWidth, 4500);

        // Get resize resolution froms first file.
        $finalResolution = $tifFiles[0]->resolution > 720 ? 720 : $tifFiles[0]->resolution;

        if (!file_exists('tmp')) {
            mkdir('tmp');
        }
        if (!file_exists('tmp2')) {
            mkdir('tmp2');
        }


        //  Set up ImageMagick command for each input file
        $cmdArray      = [];
        $outputFileArr = [];
        for ($f = 0; $f < count($tifFiles); $f++) {
            $outputFileArr[$f] = escapeshellarg('tmp/' . basename($tifFiles[$f]->name));
            $cmdArray[$f]      = <<<CMD
magick \
  {$tifFiles[$f]->nameFrame} \
  -alpha off -colorspace gray \
  -despeckle \
  -background white \
  -deskew 80% \
  -blur 0x1.1 \
  -threshold 50% \
  -define trim:percent-background=99.1% \
  -trim +repage \
  {$outputFileArr[$f]}
CMD;
        }

        //  Process each input file separately
        $runner = new ProcessRunner($cmdArray, $workingDir);
        $runner->run(); //  Returns when all processes are finished


        $cmdArray       = [];
        $outputFileArr2 = [];
        for ($f = 0; $f < count($tifFiles); $f++) {
            $info               = new ImageFileInfo('tmp/' . basename($tifFiles[$f]->name));
            $outputFileArr2[$f] = escapeshellarg('tmp2/' . basename($info->name));

            $resize       = round(($averageWidth / $info->width) * 100.0, 1);
            $resizeOption = $resize != 100.0 ? "-adaptive-resize {$resize}%" : '';

            $cmdArray[$f] = <<<CMD
magick \
  {$outputFileArr[$f]} \
  {$resizeOption} \
  -blur 0x1.6 \
  -threshold 50% \
  -bordercolor white -border 0.2% \
  -depth 1 \
  -compress Group4 \
  {$outputFileArr2[$f]};
rm {$outputFileArr[$f]}
CMD;
        }

        $runner = new ProcessRunner($cmdArray, $workingDir);
        $runner->run(); //  Returns when all processes are finished


        chdir($workingDir);
        exec('magick ' . implode(' ', $outputFileArr2) .
            " -density {$finalResolution}x{$finalResolution} -units pixelsperinch output.pdf");

        exec('rm -rf tmp tmp2');

        echo 'runtime: ', $startTime->diff(new DateTime())->format('%h:%I:%S'), PHP_EOL;
    }

    public function testAction()
    {
//        $runner = new ProcessRunner(['sleep 5', 'sleep 6', 'sleep 4', 'sleep 8', 'sleep 2', 'sleep 5', 'sleep 15', 'sleep 5', 'sleep 20', 'sleep 5', 'sleep 1', 'sleep 5']);
//        $runner->run();

    }

}
