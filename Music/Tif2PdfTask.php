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
    public function mainAction(...$args)
    {
        if (count($args) != 1) {
            $this->helpAction();
            return;
        }

        $startTime = new DateTime();


        // Some ImageMagick functions do not like spaces in file names
        // so with this we can avoid spaces in the parent directories.
        // A bug report needs to be filed with ImageMagick.
        $workingDir = $args[0];
        chdir($workingDir);
        $fNames = glob('*.tif', GLOB_ERR);
        natsort($fNames); //  so numbers don't need leading zeros

        // Turn file names into TiffFileInfo objects.
        $tifFiles = [];
        foreach ($fNames as $fName) {
            $tifFiles[] = new TiffFileInfo($fName);
        }

        $averageWidth = 0;
        for ($f = 0; $f < count($tifFiles); $f++) {
            $averageWidth += $tifFiles[$f]->width;  //  from widest frame
        }
        $averageWidth = round($averageWidth / count($tifFiles));
//        $averageWidth = min($averageWidth, 4500); // 5400?


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
  -threshold 50% \
  -define trim:percent-background=99.1% \
  -trim +repage \
  {$outputFileArr[$f]}
CMD;
        }
//  -blur 0x1.1 \

        //  Process each input file separately
        $runner = new ProcessRunner($cmdArray, $workingDir);
        $runner->run(); //  Returns when all processes are finished


        $cmdArray       = [];
        $outputFileArr2 = [];
        for ($f = 0; $f < count($tifFiles); $f++) {
            $info               = new TiffFileInfo('tmp/' . basename($tifFiles[$f]->name));
            $outputFileArr2[$f] = escapeshellarg('tmp2/' . basename($info->name));

            $resize       = round(($averageWidth / $info->width) * 100.0, 1);
            $resizeOption = $resize != 100.0 ? "-adaptive-resize {$resize}%" : '';

            $cmdArray[$f] = <<<CMD
magick \
  {$outputFileArr[$f]} \
  {$resizeOption} \
  -threshold 50% \
  -bordercolor white -border 0.2% \
  -depth 1 \
  -compress Group4 \
  {$outputFileArr2[$f]};
#rm {$outputFileArr[$f]}
CMD;
            echo $cmdArray[$f], PHP_EOL;
        }
//  -blur 0x1.6 \
        $runner = new ProcessRunner($cmdArray, $workingDir);
        $runner->run();
        $runner->wait();


        $resolution = $this->inputParams->resolution ?? 600;
        chdir($workingDir);
        exec('magick ' . implode(' ', $outputFileArr2) .
            " -density $resolution -units pixelsperinch output.pdf");

//        exec('rm -rf tmp tmp2');

        echo 'runtime: ', $startTime->diff(new DateTime())->format('%h:%I:%S'), PHP_EOL;
    }

    public function testAction()
    {
//        $runner = new ProcessRunner(['sleep 5', 'sleep 6', 'sleep 4', 'sleep 8', 'sleep 2', 'sleep 5', 'sleep 15', 'sleep 5', 'sleep 20', 'sleep 5', 'sleep 1', 'sleep 5']);
//        $runner->run();

    }

}
