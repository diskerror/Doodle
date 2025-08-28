<?php

namespace Music;

use Diskerror\Typed\Scalar\TStringTrim;

class TKeywords extends TStringTrim
{
    public function set(mixed $in): void
    {
        parent::set($in);

        // change comma and any count of white space to a single comma-space
        $this->set(preg_replace('/,\s*/', ', ', $this->_value));
    }

}
