<?php

// Check if composer is directly accessible. If not, do nothing.
if (exec('which composer') !== '') {

    // Get the last time autoload files were updated
    $autoload_time = 0;
    foreach (glob(__DIR__ . '/vendor/composer/autoload_*.php') as $file) {
        if (filemtime($file) > $autoload_time) {
            $autoload_time = filemtime($file);
        }
    }

    // Get the last file change time.
    $taskfile_time = 0;
    //  Get all Doodle root level directories, except "vendor", then recurse through them
    foreach (glob(__DIR__ . '/[A-Za-uw-z]*', GLOB_ONLYDIR) as $directory) {
        $dirItr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($dirItr as $filename) {
            if (str_ends_with($filename, '.php') && filemtime($filename) > $taskfile_time) {
                $taskfile_time = filemtime($filename);
            }
        }
    }

    // If there are newer files then update
    if ($taskfile_time > $autoload_time) {
        $current_dir = getcwd();
        chdir(__DIR__);
        exec('composer dump-autoload --optimize --no-cache --strict-ambiguous -n -q --apcu');
        chdir($current_dir);
    }
}

require_once __DIR__ . '/vendor/autoload.php';
