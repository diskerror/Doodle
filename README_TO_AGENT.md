# Doodle — Agent Guide

A CLI utility project. Collection of tasks organized by topic namespace.

## Architecture

```
doodle                  # entry point (#!/usr/bin/env php)
app/
  App.php               # Symfony Console + GetOptionKit bootstrap, auto-discovers tasks
  TaskMaster.php        # base class for all tasks
  options.php           # global CLI options (help, verbose, quiet, debug)
  config.php            # app config (process paths, caches)
  LoggerFactory.php     # logging
autoload_check.php      # auto-runs composer dump-autoload if any PHP file changed
composer.json           # PHP >=8.3, dependencies
```

## Creating a Task

### 1. Pick a namespace directory

Tasks live in top-level directories that act as namespaces:
- `Forensics/` — data forensics, parsing, analysis
- `Music/` — music file processing (PDF, metadata)
- `Xml/` — Any XML focused processing
- `Files/` — file utilities
- (Create new directories as needed)

### 2. Create `{Name}Task.php`

The filename determines the command name:
- `MedicalTimelineTask.php` → `forensics:medical-timeline` (via `doodle`)
- `StripMusicTask.php` → `xml:strip-music` (via `doodle`)
- `MainTask.php` → just the namespace name (e.g. `music`)

CamelCase class name → kebab-case command name, automatically.

### 3. Minimal template

```php
<?php

namespace Forensics;  // Must match directory name exactly

use Application\TaskMaster;

class ExampleTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => 'i|input:', 'desc' => 'Input file path', 'isa' => 'String'],
        ['spec' => 'o|output:', 'desc' => 'Output file (default: stdout)', 'isa' => 'String'],
    ];

    /**
     * One-line description shown in `doodle list`.
     *
     * Detailed help text goes in subsequent docblock lines.
     * Lines starting with @ are skipped. The first non-empty,
     * non-tag line becomes the command description.
     */
    public function mainAction(...$args): void
    {
        // Access options
        $input  = $this->getOption('input');
        $verbose = $this->getOption('verbose', 0);
        
        // Access injected services
        $basePath = $this->basePath;    // project root
        $logger   = $this->logger;      // LoggerFactory
        $options  = $this->options;      // full OptionResult
        
        // Positional arguments (after the command name)
        // Available in $args or $this->options->arguments
        
        // Output
        echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
```

### 4. Sub-actions

Methods named `{verb}Action` become sub-commands:

```php
public function helpAction(): void { /* doodle forensics:example help */ }
public function exportAction(...$args): void { /* doodle forensics:example export */ }
```

The first positional argument is checked against method names. If it matches `{arg}Action`, that method is called with remaining args.

## Running

```bash
cd ~/PhpStormProjects/Doodle

# List all commands
php doodle list

# Run a task
php doodle forensics:medical-timeline -i file.md

# With global options
php doodle forensics:medical-timeline -i file.md --verbose --debug
```

## Key Conventions

- **Namespace = directory** — `namespace Forensics;` must match `Forensics/` directory.
- **Output to stdout** — tasks print to stdout by default. Use `-o` for file output if needed.
- **JSON output** — prefer JSON for structured data. Use `JSON_PRETTY_PRINT`.
- **No framework deps beyond Symfony Console + GetOptionKit** — keep tasks self-contained.
- **Autoloading is automatic** — `autoload_check.php` runs `composer dump-autoload` when files change. No manual step needed.
- **`$this->inputParams`** — alias for `$this->options` (backward compat).
- **Positional args** — accessed via `$this->options->arguments` (array of Argument objects, use `->arg` for the value).
- **Error output** — use `fprintf(STDERR, ...)` for errors, keep stdout clean for data.

## Dependencies

From `composer.json`:
- `php-ds/php-ds` — efficient data structures (Ds\Vector, Ds\Map, etc.)
- `corneltek/getoptionkit` — CLI option parsing
- `diskerror/typed` — typed data structures (local path dep)
- `smalot/pdfparser` — PDF text extraction (local path dep)
- Symfony Console — command framework

## Option Spec Format (GetOptionKit)

```php
['spec' => 'i|input:',   'desc' => '...', 'isa' => 'String']   // -i or --input, requires value
['spec' => 'v|verbose',  'desc' => '...', 'isa' => 'boolean']   // flag, no value
['spec' => 'n|count:',   'desc' => '...', 'isa' => 'Number']    // numeric value
```

The `:` suffix means "requires a value". No `:` means boolean flag.

## Library Utilities

- `Library\StdIo` — stdout/stderr helpers (`StdIo::outln()`, etc.)
- `Library\Exceptions\BadFileException` — standard file error

## Project Location

`~/PhpStormProjects/Doodle` — GitHub: `diskerror/doodle`
