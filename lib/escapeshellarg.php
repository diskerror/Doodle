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
            return strtr($in, $escPairs);
        }

        return array_map(
            function ($v) use ($escPairs) {
                return strtr((string)$v, $escPairs);
            },
            (array)$in);
    }

};
