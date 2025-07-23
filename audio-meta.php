#!/usr/bin/env php
<?php

use Application\App;

require 'vendor/diskerror/autoload/autoload.php';

$GLOBALS ['options'] = [
    [
        'spec' => 'p|print',
        'desc' => 'Echo or print command rather than executing.',
        'type' => 'boolean',
        'defaultValue' => false,
    ],
];

$app     = new App();
$argv[0] = 'AudioMetaData';
$app->run($argv);
