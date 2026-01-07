<?php

namespace Recordings;

use Application\TaskMaster;
use Recordings\DataStruct\RecordingRecord;

class CheckTask extends TaskMaster
{
    public function mainAction(...$args): void
    {
        $this->logger->info('Recordings MainTask checkAction');

        $db = new RecordingProjectsAccess();
        $data = $db->getWhere('main_id > 0'); // get all

    }
}
