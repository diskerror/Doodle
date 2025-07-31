<?php

namespace Recordings\DataStruct;

use ArrayAccess;
use Diskerror\Typed\ConversionOptions;
use Diskerror\Typed\Date;
use Diskerror\Typed\Scalar\TBoolean;
use Diskerror\Typed\Scalar\TStringTrim;
use Diskerror\Typed\TypedClass;

/**
 * Class RecordingRecord
 *
 * This implimentation of adding ArrayAccess is awkward but
 * makes the code more readable when used.
 *
 * Using ArrayAccess returns an escaped version of the stored value.
 * Object access returns the normal value.
 *
 * @package Recordings\DataStruct
 */
class RecordingRecord extends TypedClass implements ArrayAccess
{
    protected TString64   $tape;            // name of tape, arbitrary max length
    protected TString64   $location;        // recording address
    protected DateTime    $recorded_on;     // date tape was recorded, Recordings\DataStruct\DateTime, YYYY-MM-DD[ HH:MM]
    protected TString20   $reference;       // may include device serial number and model name, will have date appended (12 char, total 32)
    protected TString8    $medium;          // tape medium type, VHS, 8MM, DAT
    protected TString8    $encoding;        // tape encoding type, PCM, FM, analog tape
    protected Date        $loaded_on;       // date session was loaded, Recordings\DataStruct\DateTime, only date is used
    protected TString64   $session;         // session directory name, arbitrary max length
    protected TStringTrim $notes;           // notes
    protected Date        $edited_on;       // date session was edited, Recordings\DataStruct\DateTime, only date is used
    protected Date        $uploaded_on;     // date audio was uploaded, Recordings\DataStruct\DateTime, only date is used
    protected TString32   $title;           // title
    protected TString256  $description;     // description
    protected TString64   $performers;      // group that was recorded

    public function __construct(mixed $in = [])
    {
        parent::__construct($in);
        $this->conversionOptions = new ConversionOptions(ConversionOptions::DATE_TO_STRING);
    }

    /**
     * Methods for ArrayAccess
     */

    public function offsetSet($offset, $value): void
    {
        $this->__set($offset, $value);
    }

    public function offsetExists($offset): bool
    {
        return $this->__isset($offset);
    }

    public function offsetUnset($offset): void
    {
        $this->__unset($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->__isset($offset) ? escapeshellarg($this->__get($offset)) : "''";
    }

}
