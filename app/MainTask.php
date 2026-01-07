<?php

namespace Application;

use Library\StdIo;

/**
 * Displays help from all projects.
 */
final class MainTask extends TaskMaster
{
    public function mainAction(...$args): void
    {
        $this->helpAction();
    }

    /**
     * Displays help from all projects.
     */
    public function helpAction(): void
    {
        // gather all namespaces
        $namespaceDirs = glob($this->basePath . '/[A-Z]*', GLOB_ONLYDIR);
        foreach ($namespaceDirs as $namespaceDir) {
            $namespace    = basename($namespaceDir);
            $mainTaskFile = $this->basePath . '/' . $namespace . '/MainTask.php';
            if (file_exists($mainTaskFile)) {
                StdIo::outln($namespace);
                $className = $namespace . '\\MainTask';
                (new $className())->helpAction();
            }
        }
    }
}
