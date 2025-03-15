#!/usr/bin/env php
<?php

use Application\App;

require 'vendor/diskerror/autoload/autoload.php';

$app = new App(__DIR__);
$app->run($argv);
