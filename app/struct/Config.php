<?php

namespace Application\Structure;

use Application\Structure\Config\Process;
use Diskerror\Typed\TypedClass;

class Config extends TypedClass
{
    protected string  $appOptionsFile    = '';
    protected Process $process;
}
