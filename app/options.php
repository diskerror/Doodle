<?php

//  Each associative array contains the keys: spec, desc, type, default, and inc.
//  Unset members will be set to null.

return [
    [
        "spec" => "h|help",
        "desc" => "Show this help.",
        'type' => 'boolean',
        'defaultValue' => false,
    ],
    [
        "spec" => "v|verbose",
        "desc" => "Verbose output (if implemented).",
        "incremental" => true,
        'defaultValue' => 0,
    ],
    [
        'spec' => 'q|quiet',
        'desc' => 'Minimal output (if implemented).',
        'defaultValue' => 0,
    ],
    [
        'spec' => 'debug',
        'desc' => 'Show debug information.',
        'type' => 'boolean',
        'defaultValue' => 0,
    ],
];
