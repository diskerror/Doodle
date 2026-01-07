#!php
<?php

use Application\App;

include __DIR__ . '/autoload_check.php';

$GLOBALS ['options'] = [
    [
        'spec' => 'p|print',
        'desc' => 'Echo or print command string rather than executing.',
        'defaultValue' => false,
    ],
    [
        'spec' => 'r|resolution:=number',
        'desc' => 'Set alternate resolution (density) for PDF. (BuildPdfTask)',
        'defaultValue' => 600,
    ],
];

$app = new App();
$app->run($argv);

exit(0);
