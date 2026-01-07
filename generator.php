#!/usr/bin/env php
<?php

use Application\App;

include __DIR__ . '/autoload_check.php';

$app = new App();
$app->run($argv);

exit(0);
