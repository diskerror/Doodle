#!/usr/bin/env bash

# Convert to 1-bit file.
# convert in.tif -monochrome -depth 1 out.tif

if  [ $# -le 1 ]; then
  echo "need input"
  exit
fi

if  [ ! -f "$1" ]; then
  echo "bad input"
  exit
fi

function kill_subprocesses() {
  local PID
  for PID in $CHILD_PID; do
    if [[$(kill -0 $PID) < = /dev/null]]; then
      kill -SIGKILL $PID
    fi
  done
  pkill -P $$
  printf '\n'
  exit
}

trap 'kill_subprocesses' SIGHUP SIGINT SIGTERM SIGQUIT SIGTSTP SIGSTOP

function doConvert() {
  nice magick "$1" -threshold 50% -depth 1 -compress Group4 "$1"
}

SRC_DIR=$(realpath "$1")

for SRC_FILE in $@; do
  (
    doConvert "$SRC_FILE"
  ) &
done

echo
