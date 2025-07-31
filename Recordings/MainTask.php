<?php

namespace Recordings;

use Application\TaskMaster;

class MainTask extends TaskMaster
{
    private const string ME_MYSELF = "'Reid Woodbury Jr.'";

    //  me - tape - performers - file
    private const string PDF_CMD = <<<'PDF_CMD'
        exiftool -overwrite_original_in_place \
          -Creator=%s \
          -Title=%s \
          -Author=%s \
          -Subject='concert program scan' \
          %s
        
        PDF_CMD;

    //  me - reference - recorded_on=YmdHi - recorded_on=Y-m-d - recorded_on=H:i:s - description
    //  performers - recorded_on=Y-m-d - medium - encoding - me - me - file
    // --MD5-Embed-Overwrite
    private const string WAV_CMD = <<<'WAV_CMD'
        bwfmetaedit --MD5-Embed-Overwrite \
          --Originator=%s \
          --OriginatorReference=%s%s \
          --OriginationDate=%s \
          --OriginationTime=%s \
          --Description=%s \
          --ICMS=%s \
          --ICRD=%s \
          --IMED=%s-%s \
          --IENG=%s \
          --ITCH=%s \
          %s
        
        WAV_CMD;

    //  me - tape - performers - description - recorded_on - uploaded_on - file
    private const string MOV_CMD = <<<'MOV_CMD'
        exiftool -overwrite_original_in_place \
          -Creator=%s \
          -Title=%s \
          -Artist=%s \
          -Description=%s \
          -CreateDate=%s \
          -MediaCreateDate=%s \
          %s
        
        MOV_CMD;


    /**
     * Attach recording projects' meta-data to the their final files.
     *
     * @return void
     */
    public function mainAction(...$params)
    {
        $this->logger->info('Recordings MainTask mainAction');

        if (count($params) < 1) {
            $this->helpAction();
            return;
        }

        $db = new RecordingProjectsAccess();

        foreach ($params as $param) {
            if (!is_file($param)) {
                $this->logger->info($param . ' not good');
                continue;
            }

            $info = pathinfo($param);

            // 'filename' is without extension, which is 'session', the unique identifier in the database
            $record = $db->getSessionRecord($info['filename']);

            if ($record === null) {
                $this->logger->info('No project record found for ' . $param);
                continue;
            }

            $this->logger->info('Processing ' . $param);

            $escapedName = escapeshellarg($param);

            $exec = $this->inputParams->print ? 'printf' : 'exec';

            switch ($info['extension']) {
                case 'txt':
                    // only do touch
                    break;

                case 'pdf':
                case 'jpg':
                    $exec(sprintf(
                              self::PDF_CMD, self::ME_MYSELF, $record['title'], $record['performers'], $escapedName
                          ));
                    break;

                case 'wav':
                    $exec(sprintf(
                              self::WAV_CMD,
                              self::ME_MYSELF, $record->reference, $record->recorded_on->format('YmdHi'),
                              $record->recorded_on->format('Y-m-d'), $record->recorded_on->format('H:i:s'),
                              $record['description'],
                              $record['performers'], $record->recorded_on->format('Y-m-d'),
                              $record->medium, $record->encoding,
                              self::ME_MYSELF, self::ME_MYSELF,
                              $escapedName
                          ));
                    break;

                case 'mov':
                    $exec(sprintf(
                              self::MOV_CMD,
                              self::ME_MYSELF, $record['title'], $record['performers'], $record['description'],
                              $record->recorded_on->format('"Y-m-d H:i:00"'),
                              $record->edited_on->format('"Y-m-d 15:00:00"'),
                              $escapedName
                          ));
                    break;

                default:
                    break;
            }

            $exec('touch -t ' . $record->recorded_on->format('YmdHi') . ' ' . $escapedName . "\n");
        }
    }

    public function checkAction()
    {
        $this->logger->info('Recordings MainTask checkAction');

        $db   = new RecordingProjectsAccess();
        $data = $db->getWhere(1); // get all

        foreach ($data->fetchArray(SQLITE3_ASSOC) as $record) {
            try {
                new RecordingRecord($record);
            }
            catch (\Exception $e) {
                continue;
            }
        }
    }
}
