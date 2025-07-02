#!/usr/bin/env bash

if [ $# -eq 0 ]; then
  echo "need input"
  exit
fi

if [ ! -d $1 ]; then
  echo "bad input"
  exit
fi

TESTPATH=$(realpath $1)
OUTPUTFILE="$TESTPATH/file_hash_output.tsv"
echo -n '' > $"$OUTPUTFILE"

for file in $(find . -type f); do
  SIZE=$(wc -c < $"$file")
  if [ $SIZE -gt 4096 ]; then
    HASH=$(sha256sum $"$file")
    echo -e $"$file"'\t'${HASH:0:64}'\t'$SIZE >> $"$OUTPUTFILE"
  fi
done < <(find . -iname 'foo*' -print0)
