#!env php
<?php
/**
 * Test: How static class properties vs static function variables
 * behave across parent/child classes and multiple instances.
 */

class Parent_Class
{
    // Static CLASS property — shared across all instances, but how across children?
    protected static int $classCounter = 0;

    public function __construct()
    {
        static::$classCounter++;
        echo get_called_class() . "::__construct() — static class property \$classCounter = " . static::$classCounter . "\n";
    }

    public function incrementFunctionStatic(): void
    {
        // Static FUNCTION variable — persists across calls, but per-class or per-method?
        static $funcCounter = 0;
        $funcCounter++;
        echo get_called_class() . "::incrementFunctionStatic() — static function var \$funcCounter = $funcCounter\n";
    }

    public function getClassCounter(): int
    {
        return static::$classCounter;
    }
}

class ChildA extends Parent_Class
{
    // No override — inherits parent's static $classCounter
}

class ChildB extends Parent_Class
{
    // Own static property — does this shadow the parent's?
    protected static int $classCounter = 0;
}

echo "=== Creating instances ===\n";

echo "\n--- Parent instance 1 ---\n";
$p1 = new Parent_Class();
$p1->incrementFunctionStatic();

echo "\n--- Parent instance 2 ---\n";
$p2 = new Parent_Class();
$p2->incrementFunctionStatic();

echo "\n--- ChildA instance 1 ---\n";
$a1 = new ChildA();
$a1->incrementFunctionStatic();

echo "\n--- ChildA instance 2 ---\n";
$a2 = new ChildA();
$a2->incrementFunctionStatic();

echo "\n--- ChildB instance 1 ---\n";
$b1 = new ChildB();
$b1->incrementFunctionStatic();

echo "\n--- ChildB instance 2 ---\n";
$b2 = new ChildB();
$b2->incrementFunctionStatic();

echo "\n=== Final state ===\n";
echo "Parent_Class::\$classCounter = " . $p1->getClassCounter() . "\n";
echo "ChildA::\$classCounter       = " . $a1->getClassCounter() . "\n";
echo "ChildB::\$classCounter       = " . $b1->getClassCounter() . "\n";

echo "\n=== Calling incrementFunctionStatic again on different instances ===\n";
echo "a1: "; $a1->incrementFunctionStatic();
echo "b1: "; $b1->incrementFunctionStatic();
echo "a2: "; $a2->incrementFunctionStatic();
echo "p1: "; $p1->incrementFunctionStatic();
