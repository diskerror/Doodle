<?php

if (!isset($GLOBALS['appName'])) {
    $GLOBALS['appName'] = 'doodle';
}

return [

    //	Relative path to the app common options file.
    'appOptionsFile' => '/app/options.php',

    'process' => [
        'name' => $GLOBALS['appName'],
        'path' => '/var/run/',
        'procDir' => '/proc/',    //	location of actual PID
    ],

    'caches' => [
        'index' => [
            'back' => [
                'cacheDir' => '/dev/shm/' . $GLOBALS['appName'] . '/',
            ],
        ],

        'summary' => [
            'back' => [
                'cacheDir' => '/dev/shm/' . $GLOBALS['appName'] . '/',
            ],
        ],
    ],

];
