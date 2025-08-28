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
        'spec' => 'r|resolution:=number',
        'desc' => 'Set alternate resolution (density) for PDF.',
        'defaultValue' => 600,
    ],
    [
        'spec' => 'b|blank',
        'desc' => 'Add a blank page to the head of the PDF.',
        'defaultValue' => false,
    ],
    [
        'spec' => 't|toc:=file',
        'desc' => 'File with new TOC. (TocTask)',
    ],
];

$app = new App();
$app->run($argv);

exit(0);
