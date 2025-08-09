#!/usr/bin/env php
<?php

use Application\App;

include __DIR__ . '/autoload_check.php';

$GLOBALS ['options'] = [
    [
        'spec' => 'p|print',
        'desc' => 'Echo or print command rather than executing.',
        'defaultValue' => false,
    ],
    [
        'spec' => 'r|resolution',
        'desc' => 'Set alternate resolution (density) for PDF. Default is 600.',
		'defaultValue' => 600
    ],
];

$app = new App();
$app->run($argv);

exit(0);
