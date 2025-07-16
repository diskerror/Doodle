<?php

namespace AudioMetaData\DataStruct;

use Diskerror\Typed\Scalar\TStringTrim;

abstract class TStringLength extends TStringTrim
{
    protected int $maxLength;

    public function set(mixed $in): void
    {
        parent::set($in);
        $length = strlen($this->value);
        if ($length > $this->maxLength) {
            throw new \OverflowException('STRING TOO LONG:'.PHP_EOL.$this->value.PHP_EOL.'HAS LENGTH '.$length.PHP_EOL.'LENGTH SHOULD BE '.$this->maxLength);
        }
    }

}
