<?php

namespace Recordings\DataStruct;

use Diskerror\Typed\TypedArray;

class RecordingRecordArray extends TypedArray
{
    protected string $_type = RecordingRecord::class;
}
