# Doodle

A monorepo of CLI tools and small projects, organized by namespace. Uses Symfony Console with convention-based auto-discovery — drop a `*Task.php` file in a namespace directory and it just works.

Each sub-project can be invoked two ways:
- Per-project script: `music.php build-pdf ...`
- Universal entry point: `doodle music:build-pdf ...`

## Features

- Convention-based auto-discovery of `*Task.php` classes (no manual registration)
- Per-task CLI options via `$taskOptions` static property
- Common CLI option handling ([GetOptionKit](https://github.com/c9s/GetOptionKit))
- Help system based on DocBlocks ([laminas-server](https://github.com/laminas/laminas-server))
- Shared library (`lib/`)
- Project scaffolding generator

## Requirements

- PHP ≥ 8.3
- Extensions: iconv, mbstring, pcntl, pdo, posix, sqlite3

## Sub-Projects

### Files

File utility tasks.

- **exif2-create** — Reads EXIF `DateTimeOriginal` or `CreateDate` tags from files and writes them to the filesystem creation date.

### Forensics

Database forensics and visualization tools, built for analyzing application internals.

- **combine-graphs** — Reads and processes GraphViz DOT files within a directory to generate a combined graph visualization. Processes content to match node names with labels and generates an output DOT file.
- **ddl2-data-dict** — Converts a DDL (Data Definition Language) file to a data dictionary.
- **dump-s-q-lite** — Dumps the contents of a SQLite database as JSON.
- **html2-ddl** — Converts HTML table definitions to DDL.
- **file** — File analysis utilities.

### Generator

Project scaffolding for creating new Doodle sub-projects.

- **project** — Creates a new sub-project with its own namespace directory, MainTask, and root script. Usage: `generator.php project [ProjectName]`
- **task** — Creates a new task within an existing project. Usage: `generator.php task [ProjectName] [TaskName]`

### Image

Image compression tools.

- **g4-in-place** — Compresses images or PDFs containing images with 1-bit Group 4 (CCITT) compression. Overwrites in place.

### Music

Tools for working with scanned music PDFs. Requires `exiftool` and ImageMagick 7.

- **build-pdf** — Gathers image files from a single directory and wraps them into a single PDF. Handles source files in parallel (limited by performance cores). Converts to 1-bit black and white.
  - Options: `-r|--resolution` (default 600), `-b|--blank` (add blank first page)
- **dump-images** — Exports embedded images from a PDF file as 8-bit grayscale.
- **fix-pdf** — Takes input PDF files, repairs them, and writes fixed copies to an output directory.
  - Options: `-p|--print` (echo command instead of executing)
- **meta-data** — Applies title, composer, and other metadata to a PDF by filename, from data in an SQLite database (`music.db`).
  - Actions: `set-db` (initialize DB — warning: deletes existing), `export` (DB → CSV/TSV), `to4s-csv` (export in forScore metadata import format with keyword extraction), `load-excel` (Excel → DB)
- **toc** — Adds a Table of Contents to a PDF file from a tab-indented source file. Numbering starts with physical page 1.
  - Options: `-t|--toc` (TOC source file)

### Recordings

Manages audio metadata for concert recording projects. Requires `exiftool` and `bwfmetaedit`. Data managed via SQLite.

- **main** — Attaches metadata from the database to final audio and PDF files.
  - Options: `-p|--print` (echo command instead of executing)
- **check** — Verification utilities.
- **csv** — Import/export between SQLite database and CSV files.
  - Actions: `export`, `import` (ignores CSV fields not in DB), `create-db`
- **load** — Creates and loads the SQLite database for tracking recording project status and metadata.
  - Actions: `create-db`, `csv` (load from original Excel spreadsheet CSV), `get-load-dates` (extract mod dates from old Pro Tools LE 8.0 `.ptf` sessions), `bparse` (parse BWF metadata commands)

### Xml

XML, MusicXML, and spreadsheet processing tools.

- **strip-music** — Strips visual noise from MusicXML files or converts to compact JSON. Supports `.musicxml`, `.xml`, and `.mxl` (compressed). JSON output includes per-instrument part data with measures, notes, dynamics, articulations, transpositions, and system-wide tempo/expression directions.
  - Options: `--pretty` (human-readable output)
- **dump-excel** — Dumps an Excel file to CSV.
- **xml** — General XML conversion utilities.
  - Actions: `to-json` (XML → JSON), `to-php` (XML → PHP `var_export()` form), `plist-to-json` (plist XML → JSON)

### Thoth *(in progress)*

Text tokenization and processing pipeline. Pre-refactor — not yet integrated into the Symfony Console framework.

### SuiteCRM *(in progress)*

Deployment scripts for SuiteCRM. Standalone shell/PHP scripts, not yet part of the framework.

## Architecture

```
doodle                  # Universal entry point
music.php               # Per-project entry point (Music namespace)
xml.php                 # Per-project entry point (Xml namespace)
...
app/
  App.php               # Symfony Console bootstrap + auto-discovery
  TaskMaster.php        # Base class for all tasks
  Reflector.php         # DocBlock-based help generation
  LoggerFactory.php     # File + STDERR logger
  options.php           # Global CLI options
lib/                    # Shared library code
Music/                  # Namespace directory
  BuildPdfTask.php      # Auto-discovered as "build-pdf" command
  MetaDataTask.php      # Auto-discovered as "meta-data" command
  ...
```

Tasks are auto-discovered by globbing `*Task.php` in each capitalized directory. Class names are converted to kebab-case command names (`BuildPdfTask` → `build-pdf`). A `MainTask` in a namespace registers as the namespace name itself (e.g., `recordings`).

## License

MIT
