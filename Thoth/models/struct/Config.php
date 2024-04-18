<?php

namespace Structure;

use Diskerror\Typed\TypedClass;
use Structure\Config\Process;

class Config extends TypedClass
{
	protected $version        = '';
	protected $userConfigName = '';
	protected $process        = [Process::class];
}
