<?php

namespace AudioMetaData;

use Application\TaskMaster;
use AudioMetaData\DataStruct\RecordingRecord;

class CheckTask extends TaskMaster
{
    public function mainAction()
    {
        $this->logger->info('AudioMetaData MainTask checkAction');

        $db = new RecordingProjectsAccess();
        $data = $db->getWhere('main_id > 0'); // get all

    }
}
