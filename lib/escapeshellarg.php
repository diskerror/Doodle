<?php

namespace Library;

if (!function_exists('Library\\escapeshellarg')) {

    /**
     * Perform shell argument escaping on string characters without adding quotes
     *
     * @param string|iterable $in
     * @return string|array
     */
    function escapeshellarg(string|iterable $in): string|array
    {
        static $escPairs = [
            ' ' => '\\ ',
            "'" => '\\\'',
            '"' => '\\"',
            '(' => '\\(',
            ')' => '\\)',
            '[' => '\\[',
            ']' => '\\]',
            '{' => '\\{',
            '}' => '\\}',
            '*' => '\\*',
            '?' => '\\?',
            '\\' => '\\\\',
        ];

        if (is_string($in)) {
            return strtr($string, $escPairs);
        }

        $returnArray = [];
        foreach ($in as $key => $value) {
            $returnArray[$key] = strtr((string)$value, $escPairs);
        }
        return $returnArray;
    }
    
};
