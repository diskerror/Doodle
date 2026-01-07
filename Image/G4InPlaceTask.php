<?php

namespace Image;

use Application\TaskMaster;
use Diskerror\Typed\DateTime;
use Ds\Vector;
use Library\ProcessRunner;
use Library\StdIo;
use function Library\escapeshellarg;

class G4InPlaceTask extends TaskMaster
{
    public function mainAction(...$args): void
    {
        if (count($args) < 2) {
            echo 'need input';
            exit;
        }

        $startTime = new DateTime();

        $commands = new Vector();

        foreach ($args as $fName) {
            if (!file_exists($fName)) {
                echo "bad input file: $fName", PHP_EOL;
                continue;
            }

            $fName = escapeshellarg($fName);

            $commands->push("magick $fName -threshold 50% -depth 1 -compress Group4 $fName");
        }

        $runner = new ProcessRunner($commands);
        $runner->run();
        $runner->wait();

        StdIo::outln('progress time: ' . $startTime->diff(new DateTime())->format('%h:%I:%S'));
    }
}
