# Gemini Context: Doodle

## Project Overview
**Doodle** is a CLI application framework and monorepo designed to manage a collection of small projects and scripts. It utilizes the **Phalcon** framework (specifically its CLI components) to structure command-line tasks.

Each "sub-project" (e.g., Music, Recordings) resides in its own directory and is typically invoked via a dedicated root-level executable (e.g., `music.php`, `recordings.php`).

## Architecture

### Core Components
*   **Entry Points:** Root scripts like `doodle`, `music.php`, `recordings.php` serve as the entry points. They instantiate `Application\App`.
*   **Application\App:** (`app/App.php`) The bootstrap class. It:
    *   Sets up the environment (error handling, signals).
    *   Initializes the dependency injection (DI) container (`Phalcon\Di\FactoryDefault\Cli`).
    *   Loads configuration (`app/config.php`).
    *   Parses command-line arguments using `GetOptionKit`.
    *   Determines the active **Namespace** based on the executing script's filename (e.g., `music.php` -> `Music` namespace).
*   **Application\Console:** (`app/Console.php`) Extends `Phalcon\Cli\Console`. It handles the routing logic, attempting to map arguments to `Task` and `Action`. It supports fallbacks (e.g., if `Task` isn't found, try treating it as `main` task with arguments).
*   **Application\TaskMaster:** Base class for tasks (implied by usage in `Music/MainTask.php`).

### Directory Structure
*   **`app/`**: Core framework code (`App.php`, `Console.php`, `TaskMaster.php`, `config.php`).
*   **`lib/`**: Shared libraries and utilities (e.g., `ProcessRunner.php`, `XmlParser.php`).
*   **`vendor/`**: Composer dependencies.
*   **`[ProjectName]/`**: Directories for specific sub-projects (e.g., `Music/`, `Recordings/`, `Image/`). Each contains its own Tasks.

## Usage

### Running Commands
There are two primary ways to invoke commands:

1.  **Dedicated Script:** Use the script matching the project name.
    ```bash
    ./music.php [task] [action] [params]
    ```
    *   This sets the namespace to `Music`.
    *   Example: `./music.php` (runs `MainTask::mainAction`)

2.  **Generic Runner:** Use the `doodle` script.
    ```bash
    ./doodle [project] [task] [action] [params]
    ```
    *   Example: `./doodle music` is equivalent to `./music.php`.

### Command Resolution Logic
The `Console::handle` method attempts to resolve commands in this order:
1.  `[task] [action] [params]`
2.  `[task] main [params]` (if action is omitted)
3.  `main main [params]` (fallback)

## Development

### Prerequisites
*   PHP 8.3+
*   Composer
*   Phalcon extension (optional but suggested, framework code handles some polyfills/dependencies).
*   Extensions: `iconv`, `mbstring`, `pcntl`, `pdo`, `posix`, `sqlite3`.

### Setup
1.  Install dependencies:
    ```bash
    composer install
    ```

### Adding a New Project
1.  Create a directory (e.g., `MyProject`).
2.  Create a `MainTask.php` inside it, extending `Application\TaskMaster`.
    ```php
    namespace MyProject;
    use Application\TaskMaster;
    class MainTask extends TaskMaster { ... }
    ```
3.  (Optional) Create a root-level script `myproject.php` copying the pattern from `music.php`.

### Testing
*   Configuration is in `phpunit.xml.dist`.
*   **Note:** The `tests/` directory referenced in the config does not currently exist in the file listing. Ensure it is created before running `vendor/bin/phpunit`.

## Key Dependencies
*   `corneltek/getoptionkit`: Command line option parsing.
*   `laminas/laminas-server`: Protocol servers (used for help system).
*   `diskerror/autoload`: Custom autoloader.
*   `diskerror/typed`: Type checking utility.
