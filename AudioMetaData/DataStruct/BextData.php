<?php

namespace AudioMetaData\DataStruct;

use AudioMetaData\TInteger;
use AudioMetaData\TString;
use Diskerror\Typed\Scalar\TIntegerUnsigned;
use Diskerror\Typed\TypedClass;


class BextData extends TypedClass
{
    protected TString256       $Description;
    protected TString32        $Originator;
    protected TString32        $OriginatorReference;
    protected TString10        $OriginatorDate;
    protected TString8         $OriginatorTime;
    protected TIntegerUnsigned $TimeReference;
    protected TIntegerUnsigned $Version;
    protected TString64        $UMID;
    protected TInteger         $LoudnessValue;
    protected TIntegerUnsigned $LoudnessRange;
    protected TInteger         $MaxTruePeakLevel;
    protected TInteger         $MaxTruePeakLevel;
    protected TInteger         $MaxMomentaryLoudness;
    protected TInteger         $MaxShortTermLoudness;
    protected TString          $Reserved;       //  Max length 180, ignore for now
    protected TString          $CodingHistory;  //  Any string, ignore for now
}
