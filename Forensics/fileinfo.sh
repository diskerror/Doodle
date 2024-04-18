#!/usr/bin/env bash

# Get extensive information about a file or directory of files

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <file>"
  exit 1
fi

# Set likely timezone for file info.
tz=America/New_York

function getInfo() {
  fName="$1"
  if [[ ! -e "$fName" ]]; then
    echo "File/directory not found: $fName" >&2
    exit 1
  fi

  # Full path
  echo -ne "$fName\t"

  # File name if it's not a directory
  if [[ ! -d "$fName" ]]; then
    echo -n $(basename "$fName")
  fi

  echo -ne $'\t'

  # MD5 if it's not a directory
  if [[ ! -d "$fName" ]]; then
    echo -n $(md5 -q "$fName")
  fi

  # Rest of the info
  #    TZ=$tz stat -n -f $'\t%z\t%SB\t%Sm\t%Sa\t' -t "%F %T" "$fName"
  TZ=$tz stat -n -f $'\t%z\t%SB\t%Sm\t' -t "%F %T" "$fName"
  file -bkp "$fName"
}

# If input is a file then print the info
if [[ -f "$1" ]]; then
  getInfo "$1"
  exit 0
fi

# Header row.
#echo -e 'Path to File\tFile Name\tMD5 Fingerprint\tSize\tCreated on Date\tModified on Date\tLast Access Date\tOther info'
echo -e 'Path to File\tFile Name\tMD5 Fingerprint\tSize\tCreated on Date\tModified on Date\tOther info'

# If input is a directory then print the info for each file in the directory, recursively.
IFS=$'\n'
for fileName in $(find "$1" ! -name '._*'); do
  getInfo "${fileName}"
done

exit 0
