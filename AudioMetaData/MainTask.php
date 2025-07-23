<?php

namespace AudioMetaData;

use Application\TaskMaster;

class MainTask extends TaskMaster
{
    private const ME = "'Reid Woodbury Jr.'";

    //  me - tape - performers - file
    private const PDF_CMD = <<<'PDF_CMD'
        exiftool -overwrite_original_in_place \
            -Creator=%s -Title=%s -Author=%s -Subject='concert program' %s
        
        PDF_CMD;

    //  me - reference - recorded_on=YmdHi - recorded_on=Y-m-d - recorded_on=H:i:s - description
    //  performers - recorded_on=Y-m-d - medium - encoding - me - me - file
    private const WAV_CMD = <<<'WAV_CMD'
        bwfmetaedit --MD5-Embed-Overwrite \
            --Originator=%s --OriginatorReference=%s%s --OriginationDate=%s --OriginationTime=%s --Description=%s \
            --ICMS=%s --ICRD=%s --IMED=%s-%s --IENG=%s --ITCH=%s %s
        
        WAV_CMD;

    //  me - tape - performers - description - recorded_on - uploaded_on - file
    private const MOV_CMD = <<<'MOV_CMD'
        exiftool -overwrite_original_in_place \
            --Creator=%s Title=%s -Artist=%s -Description=%s -CreateDate=%s -MediaCreateDate=%s\ 15:00 %s
        
        MOV_CMD;


    /**
     * Attach recording projects' meta-data to the their final files.
     *
     * @return void
     */
    public function mainAction(...$params)
    {
        $this->logger->info('AudioMetaData MainTask mainAction');

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
                    $exec(sprintf(self::PDF_CMD, self::ME, $record['tape'], $record['performers'], $escapedName));
                    break;

                case 'wav':
                    $exec(sprintf(
                              self::WAV_CMD,
                              self::ME, $record->reference, $record->recorded_on->format('YmdHi'),
                              $record->recorded_on->format('Y-m-d'), $record->recorded_on->format('H:i:s'),
                              $record['description'],
                              $record['performers'], $record->recorded_on->format('Y-m-d'),
                              $record->medium, $record->encoding,
                              self::ME, self::ME, $escapedName
                          ));
                    break;

                case 'mov':
                    $exec(sprintf(
                              self::MOV_CMD,
                              self::ME, $record['tape'], $record['performers'], $record['description'],
                              $record->recorded_on->format('Y-m-d'), $record->uploaded_on->format('Y-m-d'),
                              $escapedName
                          ));
                    break;

                default:
                    break;
            }

            $exec('touch -t ' . $record->recorded_on->format('YmdHi') . ' ' . $escapedName . "\n");
        }
    }
}
