<?php

namespace Music;

use Application\TaskMaster;
use Diskerror\Typed\DateTime;
use ErrorException;
use Library\ProcessRunner;
use Library\StdIo;

include 'lib/var_export.php';


class ImslpTask extends TaskMaster
{
    /**
     * Converts all '.tif' files in a directory to a single '.pdf'.
     *
     * @return int
     * @throws ErrorException
     */
    public function mainAction(...$args)
    {
        $this->logger->info('Music ImslpTask mainAction');

        if (count($args) != 1) {
            $this->helpAction();
            return;
        }

        $startTime = new DateTime();

        $tmpSuffix = '_TMP.tif';

        // Set trim parameters
        $frac = round(0.02 / 100.0, 4);  // '.02%' trim margin remainder fraction (percent to fraction)
        $size = $frac + 1.0;


        // Some ImageMagick functions do not like spaces in file names
        // so with this we can avoid spaces in the parent directories.
        // A bug report needs to be filed with ImageMagick.
        chdir($args[0]);

        /////////////////////////////////////////////////////////////
        //  Deskew each input file separately
        StdIo::outln('Deskewing...');
        $cmdArray = [];
        foreach (glob('image-[0-9][0-9][0-9].tif', GLOB_ERR) as $fName) {
            $fName = $this->escapeShellArg($fName);

            $cmdArray[] = <<<CMD
                magick $fName \
                    -alpha off -colorspace gray -depth 8 \
                    -virtual-pixel white -background white \
                    -deskew 80% +repage \
                    -set filename:fname "%t{$tmpSuffix}" \
                    '%[filename:fname]'
                CMD;
        }

        //  Process each input file separately
        $runner = new ProcessRunner($cmdArray);
        $runner->run();
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));


        /////////////////////////////////////////////////////////////
        //  Trim each input file separately
        StdIo::outln('Trimming...');
        foreach (glob("image-[0-9][0-9][0-9]$tmpSuffix", GLOB_ERR) as $fName) {
            $fName = $this->escapeShellArg($fName);

            $cmdArray[] = <<<CMD
                magick $fName \
                    -crop \
                    $(magick $fName -virtual-pixel white -blur 0x'%[fx:round(w*0.01)]' -fuzz 5% \
                      -define trim:percent-background=97% -trim \
                      -format \
                      '%[fx:round(w*$size)]x%[fx:round(h*$size)]+%[fx:round(page.x-(w*$frac))]+%[fx:round(page.y-(h*$frac))]' \
                      info:) \
                    +repage \
                    -set filename:fname "%f" \
                    '%[filename:fname]'
                CMD;
        }

        //  Wait for the previous batch to finish
        $runner->wait();

        //  Process new batch
        $runner = new ProcessRunner($cmdArray);
        $runner->run();
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));


        /////////////////////////////////////////////////////////////
        //  Find the average width of the images
        StdIo::outln('Finding and applying average width...');
        $averageWidth = 0;
        $files        = glob("image-[0-9][0-9][0-9]$tmpSuffix", GLOB_ERR);
        foreach ($files as $fName) {
            $averageWidth += (new TiffFileInfo($fName))->width;
        }
        $averageWidth = round($averageWidth / count($files));

        /////////////////////////////////////////////////////////////
        //  Resize the images to the average width
        foreach (glob("image-[0-9][0-9][0-9]$tmpSuffix", GLOB_ERR) as $fName) {
            $fName = $this->escapeShellArg($fName);

            $cmdArray[] = <<<CMD
                magick $fName \
                    -adaptive-resize '%[fx:round(($averageWidth/w)*100)]'% \
                    -set filename:fname "%f" \
                    '%[filename:fname]'
                CMD;
        }
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));

        //  Wait for the previous batch to finish
        $runner->wait();

        //  Process new batch
        $runner = new ProcessRunner($cmdArray);
        $runner->run();
        $runner->wait();


        /////////////////////////////////////////////////////////////
        //  Combine the images into a single PDF
        StdIo::outln('Combining images...');
        $cmd = <<<CMD
            magick image-[0-9][0-9][0-9]$tmpSuffix \
                -threshold 50% -depth 1 \
                -compress Group4 \
                -density {$this->inputParams->resolution} -units pixelsperinch \
                1output.pdf
            CMD;

        StdIo::outln($cmd);
        exec($cmd);
        exec("rm image-[0-9][0-9][0-9]{$tmpSuffix}");

        StdIo::outln('Total runtime: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));
    }

}
