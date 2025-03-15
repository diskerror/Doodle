#!/usr/bin/env php
<?php

echo json_encode(getimagesize($argv[1]), JSON_PRETTY_PRINT), PHP_EOL;
