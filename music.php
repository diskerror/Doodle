#!/usr/bin/env php
<?php

use Application\App;

include __DIR__ . '/autoload_check.php';

$GLOBALS ['options'] = [
    [
        'spec' => 'p|print',
        'desc' => 'Echo or print command rather than executing.',
        'type' => 'boolean',
        'defaultValue' => false,
    ],
];

$app = new App();
$app->run($argv);

exit(0);
