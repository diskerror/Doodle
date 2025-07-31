
# Doodle
One place to hold all of my little projects and scripts. It uses the Phalcon framework and its technique of creating tasks for command line commands. A subproject is invoked using a project root executable with the same name as the directory containing the project files, ie. "music.php" calls tasks in the "Music" directory.

# Features
+ Common CLI option handling (corneltek/getoptionkit).
+ Place for common files (lib directory).
+ Help system based on DocBlocks (laminas/laminas-server).
+ Caches list of commands. Automatically rebuilt on error.
+ "Application\App.php" now handles app namespaces without tasks or actions. Using "project main main [params]" can be
  called with "project [params]". Application, always sends -h or --help to the help task for that namespace.

# Featured Projects
Most of my small project files have been moved into this repository. The best of them that have been fully integrated into this framework are listed below.

## Music
Music is a collection of tools for working with scanned music PDFs.

### Tif2Pdf
Converts a singledirectory of TIFF files into a single PDF file. It handles source files in parallel limited by the number of performance cores available. It then assembles the pages into a single PDF file. Files are converted to 1-bit black and white. It requires the ImageMagick 7 command line tools.

### ApplyMeta[data]
Applies title, composer, and other metadata to a PDF by its filename from data save in an SQLite database 'music.db'.

## Recordings
This project manages audio metadata for my recording projects. It requires the command line tools:
+ exiftool: for the PDFs of the programs
+ bwfmetaedit: for the WAV audio files

Data is magaged with the SQLite database IDE in PHPStorm.

### Load
Loads data from various sources into a SQLite database.

### Main
Attaches metadata from the database to the final files.
