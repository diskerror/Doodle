<?php

namespace Application\Structure\Config;


use Diskerror\Typed\TypedClass;

/**
 * Class Process
 *
 * @param $name
 * @param $path
 * @param $procDir
 *
 * @package Application\Structure\Config
 *
 */
class Process extends TypedClass
{
    protected string $name    = '';
    protected string $path    = '';
    protected string $procDir = '';
}
