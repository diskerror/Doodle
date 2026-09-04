<?php

namespace Music;

use Application\TaskMaster;
use DateTime;
use ErrorException;
use Library\escapeshellarg;
use Library\ProcessRunner;
use Library\StdIo;


class DumpImagesTask extends TaskMaster
{
    /**
     * dumpImagesAction
     *
     * Exports images from a PDF file.
     * Shell for-loop gaurentees the images have no more detail than 8-bit grayscale.
     *
     * @return void
     * @throws ErrorException
     */
    public function mainAction(...$args): void
    {
        if (count($args) < 1) {
            $this->helpAction();
            return;
        }

        $startTime = new DateTime();

        $cmds = [];

        foreach ($args as $arg) {
            if (!is_file($arg)) {
                throw new ErrorException($arg . ' is not a file');
            }

            $pathinfo = pathinfo($arg);
            if (strtolower($pathinfo['extension']) !== 'pdf') {
                throw new ErrorException($arg . ' is not a PDF file');
            }

            //  Use PHP-native escapeshellarg to wrap each shell arg in single quotes.
            //  The custom Library\escapeshellarg backslash-escapes without wrapping,
            //  which breaks when the value is embedded inside a quoted heredoc and
            //  later expanded by the shell as "$var" — the escaped \' becomes a raw '.
            $destDir = \escapeshellarg($pathinfo['dirname'] . '/' . $pathinfo['filename']);

            $arg     = \escapeshellarg($arg);
            $cmds[]  = <<<CMD
mkdir -p $destDir
pdfimages $arg $destDir/images
cd $destDir
for fn in *; do
  tv=\$(magick identify -format "%z %r" "\$fn");
  if [[ \${tv:0:2} -gt 8 || \${tv: -4:3} == 'RGB' ]]; then
    magick "\$fn" -colorspace gray -depth 8 "\$fn"
  fi
done
CMD;
        }
		//StdIo::outln($cmds[0]);exit;

        //  Process each input file separately
        $runner = new ProcessRunner($cmds);
        $runner->run();
        $runner->wait();

        echo 'runtime: ', $startTime->diff(new DateTime())->format('%h:%I:%S'), PHP_EOL;
    }

}
