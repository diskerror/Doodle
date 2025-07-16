<?php

namespace AudioMetaData\DataStruct;

use Diskerror\Typed\DateTime as TypedDT;

class DateTime extends TypedDT
{
    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->format('Y') < 1986) {
            return '';
        }

        if ($this->format('G') == 0 && $this->format('i') == 0) {
            return substr(((array)$this)['date'], 0, 10);   //  Y-m-d
        }

        return substr(((array)$this)['date'], 0, 16);   //  Y-m-d H:i
    }

}
