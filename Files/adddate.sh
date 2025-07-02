#!/bin/bash
# This script adds a date to a file.
# Usage: ./adddate.sh <filename>
# Example: ./adddate.sh myfile.txt
# The file's creation date will be added to the start of the file name.

for f in "$@"; do
  mv "$f" $(dirname "$f")"/"$(date -r "$f" "+%Y%m%d_%H%M_")$(basename "$f")
done
