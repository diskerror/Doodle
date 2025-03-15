<?php

namespace Music;

use Application\TaskMaster;
use Library\StdIo;

class MainTask extends TaskMaster
{
    public function mainAction(): void
    {
        StdIo::outln('Main action');
    }
}
