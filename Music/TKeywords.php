<?php

namespace Music;

use Diskerror\Typed\ScalarAbstract;

class TKeywords extends ScalarAbstract
{
    public function __construct(mixed $in = '', bool $allowNull = false)
    {
        $this->_value = [];
        parent::__construct($in, $allowNull);
    }

    public function set(mixed $in): void
    {
        // change comma and any count of white space to a single comma-space
        $in = preg_replace('/,\s*/', ', ', trim((string)$in, "\x00..\x20"));

        //  Put in an array
        if ($in !== '') {
            $this->_value[] = $in;
        }
    }

    /**
     * @return string
     */
    public function get(): string
    {
        return implode(', ', $this->_value);
    }

}
