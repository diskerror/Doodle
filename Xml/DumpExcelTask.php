<?php

namespace Xml;

use Application\TaskMaster;
use Library\StdIo;
use Shuchkin\SimpleXLSX;

class DumpExcelTask extends TaskMaster
{
    /**
     * @return void
     */
    public function mainAction(...$args): void
    {
        if (count($args) !== 1) {
            StdIo::outln('needs file (only one)');
            $this->helpAction();
            return;
        }

        if ($excel = SimpleXLSX::parseFile($args[0], true)) {
            foreach ($excel->rows() as $row) {
                StdIo::outln(implode(', ', $row));
            }
        }

    }

}
