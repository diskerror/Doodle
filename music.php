#!/usr/bin/env php
<?php

use Application\App;

require 'vendor/diskerror/autoload/autoload.php';

$app = new App();
$app->run($argv);

exit(0);
