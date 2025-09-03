<?php

namespace Music;

use Application\TaskMaster;
use Diskerror\Typed\DateTime;
use Ds\Vector;
use ErrorException;
use Library\ProcessRunner;
use Library\StdIo;
use function Library\escapeshellarg;


class BuildPdfTask extends TaskMaster
{
    /**
     * mainAction
     *
     * Gathers and fixes a list of image files and wraps them into a single '.pdf'.
     *
     * All input files must be in the same directory.
     *
     * `music.php build-pdf <image-file> <image-file> ... <destination-pdf>`
     *
     * Creates destination directory if it does not exist.
     *
     * @return void
     * @throws ErrorException
     */
    public function mainAction(...$args): void
    {
        $this->logger->info('Music BuildPdfTask mainAction');

        if (count($args) < 2) {
            $this->helpAction();
            return;
        }

        $startTime = new DateTime();

        $pInfo = pathinfo(array_pop($args));

        if (!file_exists($pInfo['dirname'])) {
            mkdir($pInfo['dirname'], 0755, true);
        }

        $dirname  = realpath($pInfo['dirname']);
        $basename = strtolower($pInfo['extension'] === 'pdf' ? $pInfo['basename'] : ($pInfo['filename'] . '.pdf'));
        $destFile = escapeshellarg($dirname . '/' . $basename);

        $tmpSuffix = '_TMP.tif';


        // Some ImageMagick functions do not like spaces in file names
        // so with this we can avoid spaces in the parent directories.
        // A bug report needs to be filed with ImageMagick.
        chdir(dirname($args[0]));

        $origFiles = new Vector($args);
        $origFiles = $origFiles->map(function ($fName) {
            return pathinfo($fName, PATHINFO_BASENAME);
        });

        $tmpFiles    = $origFiles->map(function ($fName) use ($tmpSuffix) {
            $pi = pathinfo($fName);
            if ($pi['dirname'] === '.') {
                return $pi['filename'] . $tmpSuffix;
            }
            return $pi['dirname'] . '/' . $pi['filename'] . $tmpSuffix;
        });
        $tmpFilesEsc = $tmpFiles->map('Library\\escapeshellarg');
        $origFiles   = $origFiles->map('Library\\escapeshellarg');


        // Set trim parameters
        $frac = 0.008;  // '0.8%' trim margin remainder fraction (percent to fraction)
        $size = ($frac * 2.0) + 1.0;

        $commands = new Vector();


        /////////////////////////////////////////////////////////////
        //  Deskew each input file separately
        StdIo::outln('Deskewing...');
        foreach ($origFiles as $fName) {
            $resolution = exec('magick identify -format "%x" ' . $fName);
            $resizeStr  = $resolution > 600 ? ('-adaptive-resize ' . (60000.0 / $resolution) . '%') : '';

            $commands->push(
                <<<CMD
                magick $fName \
                    -alpha off -colorspace gray -depth 8 \
                    -despeckle \
                    $resizeStr \
                    -virtual-pixel white -background white \
                    -deskew 80% +repage \
                    -set filename:fname "%t{$tmpSuffix}" \
                    '%[filename:fname]'
                CMD
            );
        }
//        for($i = 0; $i < $origFiles->count(); $i++) {
//            $commands->push(
//                <<<CMD
//                cp $origFiles[$i] $tmpFiles[$i]
//                CMD
//            );
//        }

        //  Process each input file separately
        $runner = new ProcessRunner($commands);
        $runner->run();
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));


        /////////////////////////////////////////////////////////////
        //  Trim each input file separately
        StdIo::outln('Trimming...');
        $commands->clear();
        foreach ($tmpFilesEsc as $fName) {
            $commands->push(
                <<<CMD
                magick $fName \
                    -virtual-pixel white -background white \
                    -crop \
                    $(magick $fName -virtual-pixel white -blur 0x'%[fx:round(w*0.001)]' -fuzz 3% \
                      -define trim:percent-background=99.6% -trim \
                      -format \
                      '%[fx:round(w*$size)]x%[fx:round(h*$size)]+%[fx:round(page.x-(w*$frac))]+%[fx:round(page.y-(h*$frac))]' \
                      info:) \
                    +repage \
                    -set filename:fname "%f" \
                    '%[filename:fname]'
                CMD
            );
        }

        //  Wait for the first batch to finish
        $runner->wait();

        //  Process new batch
        $runner = new ProcessRunner($commands);
        $runner->run();
        $runner->wait();
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));


        /////////////////////////////////////////////////////////////
        //  Find the average width of the images
        StdIo::outln('Finding and applying average width...');
        $averageWidth = 0;
        foreach ($tmpFiles as $fName) {
            $averageWidth += explode(' ', exec('magick identify -format "%w " ' . $fName))[0];
        }
        $averageWidth = round($averageWidth / count($tmpFiles));

        /////////////////////////////////////////////////////////////
        //  Resize the images to the average width
        $commands->clear();
        foreach ($tmpFilesEsc as $fName) {
            $commands->push(
                <<<CMD
                magick $fName \
                    -adaptive-resize '%[fx:round(($averageWidth/w)*100)]'% \
                    -set filename:fname "%f" \
                    '%[filename:fname]'
                CMD
            );
        }

        //  Process new batch
        $runner = new ProcessRunner($commands);
        $runner->run();
        $runner->wait();
        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));


        /////////////////////////////////////////////////////////////
        //  Combine the images into a single PDF
        $blankOpt = $this->options->blank ? __DIR__ . '/blank.pdf ' : '';
        StdIo::outln('Combining and compressing images...');
        exec(
            <<<CMD
            magick {$blankOpt}*$tmpSuffix \
                -threshold 60% -depth 1 \
                -compress Group4 \
                -density {$this->options->resolution} -units pixelsperinch \
                $destFile
            CMD
        );
        // $this->options->resolution == 600 ? 480 ? 360 ? 240

        exec("rm *{$tmpSuffix}");

        StdIo::outln('Total runtime: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));
    }

//    public function testAction()
//    {
//        $runner = new ProcessRunner(['sleep 5', 'sleep 6', 'sleep 4', 'sleep 8', 'sleep 2', 'sleep 5', 'sleep 15', 'sleep 5', 'sleep 20', 'sleep 5', 'sleep 1', 'sleep 5']);
//        $runner->run();
//
//    }

}
