<?php

namespace AudioMetaData\DataStruct;

use Diskerror\Typed\ConversionOptions;
use Diskerror\Typed\Scalar\TBoolean;
use Diskerror\Typed\Scalar\TStringTrim;
use Diskerror\Typed\TypedClass;

class RecordingRecord extends TypedClass
{
    protected TStringTrim $tape;            // name of tape
    protected TStringTrim $location;        // recording address
    protected DateTime    $recorded_on;     // date tape was recorded, AudioMetaData\DataStruct\DateTime, YYYY-MM-DD[ HH:MM]
    protected TStringTrim $machine_code;    // machine code, if any, may include serial number and model name
    protected TStringTrim $medium;          // tape medium type, VHS, 8MM, DAT
    protected TStringTrim $encoding;        // tape encoding type, PCM, FM, analog
    protected TBoolean    $pre_emph;        // has EIAJ pre-emphasis
    protected DateTime    $loaded_on;       // date session was loaded, AudioMetaData\DataStruct\DateTime, only date is used
    protected TStringTrim $session;         // session directory name
    protected TStringTrim $notes;           // notes
    protected DateTime    $edited_on;       // date session was edited, AudioMetaData\DataStruct\DateTime, only date is used
    protected DateTime    $uploaded_on;     // date audio was uploaded, AudioMetaData\DataStruct\DateTime, only date is used
    protected TString256  $description;     // description

    public function __construct(mixed $in = [])
    {
        parent::__construct($in);
        $this->conversionOptions = new ConversionOptions(ConversionOptions::DATE_TO_STRING);

    }
}
