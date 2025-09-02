<?php

namespace Files;

use Application\TaskMaster;
use Library\StdIo;
use Library\Xml2Array;
use Shuchkin\SimpleXLSX;

class Excel2CsvTask extends TaskMaster
{
    /**
     * @return void
     */
    public function mainAction(...$args)
    {
        if (count($args) !== 1) {
            StdIo::outln('needs file (only one)');
            $this->helpAction();
            return;
        }

        if ($excel = SimpleXLSX::parseFile($args[0], true)) {
            StdIo::phpOut((array)$excel);
        }

    }

}
