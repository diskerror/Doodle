#!/usr/bin/env bash

# Look for terms in first file, one term per line, in a second file or directory.

if [[ $# -ne 2 ]]; then
  echo "Usage: $0 <file of terms> <file or directory to search> "
  exit 1
fi

terms=$(cat "$1")
placeToSearch="$2"

IFS=$'\n'
for term in $terms; do
  # 	term=${term//'_'/'\w{0,10}[_\s]{0,4}[\w\s]{0,10}'}
  # 	term=${term//'_'/'[_[:space:]]{0,4}'}
  #	term=${term//'_'/'[^,]{0,6}'}
  #	t_sub=${term//'[- ]'/'_'}
#  t_sub=${term//'_'/'[_ ,"]{0,10}'}
    t_sub=${term//'_'/'_?'}

  cmd="egrep -iroh -m 1 --exclude='.*' '$t_sub' '$placeToSearch'"
  #	echo "$cmd"
  found=$(eval "$cmd")

  if [ ! -z "$found" ]; then
    #		echo "$cmd"
    echo $term
    IFS=$'\n'
    for f in $found; do
      echo -n "$f · "
    done
    echo
    echo
  fi
done

exit 0
