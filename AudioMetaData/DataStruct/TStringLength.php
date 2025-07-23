<?php

namespace AudioMetaData\DataStruct;

use Diskerror\Typed\Scalar\TStringTrim;

abstract class TStringLength extends TStringTrim
{
    protected int $_maxLength;

    public function set(mixed $in): void
    {
        parent::set($in);
        $length = strlen($this->_value);
        if ($length > $this->_maxLength) {
            throw new \OverflowException('STRING TOO LONG:'.PHP_EOL.$this->_value.PHP_EOL.'HAS LENGTH '.$length.PHP_EOL.'LENGTH SHOULD BE '.$this->_maxLength);
        }
    }

}
