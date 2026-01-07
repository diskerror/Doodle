<?php

namespace Generator;

use Application\TaskMaster;
use Library\StdIo;

class MainTask extends TaskMaster
{
    public function mainAction(...$args): void
    {
        $this->helpAction();
    }

    /**
     * Create a new project with its own namespace and root script.
     * Usage: ./generator.php project [ProjectName]
     */
    public function projectAction(...$params): void
    {
        if (empty($params)) {
            $this->fail("Project name is required.");
            return;
        }

        $projectName = ucfirst($params[0]);
        $basePath    = $this->basePath;
        $projectDir  = $basePath . '/' . $projectName;
        $rootScript  = $basePath . '/' . strtolower($projectName) . '.php';

        if (is_dir($projectDir) || file_exists($rootScript)) {
            $this->fail("Project '$projectName' or script already exists.");
            return;
        }

        if (!$this->confirm("Create project '$projectName' in '$projectDir'?", true)) {
            return;
        }

        // 1. Create Directory
        if (!mkdir($projectDir)) {
            $this->fail("Failed to create directory.");
            return;
        }

        // 2. Create MainTask.php
        $mainTaskContent = <<<PHP
<?php

namespace {$projectName};

use Application\TaskMaster;

class MainTask extends TaskMaster
{
    /**
     * Default action.
     */
    public function mainAction(): void
    {
        \$this->info("Hello from {$projectName}!");
    }
}
PHP;
        file_put_contents($projectDir . '/MainTask.php', $mainTaskContent);

        // 3. Create Root Script
        $rootScriptContent = <<<PHP
#!/usr/bin/env php
<?php

use Application\App;

include __DIR__ . '/autoload_check.php';

\$app = new App();
\$app->run(\$argv);

exit(0);
PHP;
        file_put_contents($rootScript, $rootScriptContent);
        chmod($rootScript, 0755);

        $this->success("Project '$projectName' created successfully.");
        $this->info("Try it: ./".basename($rootScript));
    }

    /**
     * Create a new task within an existing project.
     * Usage: ./generator.php task [ProjectName] [TaskName]
     */
    public function taskAction(array $params): void
    {
        if (count($params) < 2) {
            $this->fail("Usage: task [ProjectName] [TaskName]");
            return;
        }

        $projectName = ucfirst($params[0]);
        $taskName    = ucfirst($params[1]);
        
        // Ensure "Task" suffix is handled if user omits it
        if (!str_ends_with($taskName, 'Task')) {
            $taskName .= 'Task';
        }

        $projectDir = $this->basePath . '/' . $projectName;
        $taskFile   = $projectDir . '/' . $taskName . '.php';

        if (!is_dir($projectDir)) {
            $this->fail("Project directory '$projectName' not found.");
            return;
        }

        if (file_exists($taskFile)) {
            $this->fail("Task file '$taskFile' already exists.");
            return;
        }

        if (!$this->confirm("Create task '$taskName' in '$projectName'?", true)) {
            return;
        }

        $content = <<<PHP
<?php

namespace {$projectName};

use Application\TaskMaster;

class {$taskName} extends TaskMaster
{
    /**
     * Default action.
     */
    public function mainAction(): void
    {
        \$this->info("{$taskName} is running.");
    }
}
PHP;
        file_put_contents($taskFile, $content);
        $this->success("Task '$taskName' created in '$projectName'.");
    }
}
