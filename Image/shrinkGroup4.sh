#!/usr/bin/env bash

# Convert to 1-bit file.
# convert in.tif -monochrome -depth 1 out.tif

if  [ $# -le 1 ]; then
	echo "need input"
	exit
fi

if  [ ! -d "$1" ]; then
	echo "bad src dir"
	exit
fi

if  [ ! -d "$2" ]; then
	echo "bad dest dir"
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
	nice magick "$1" -threshold 50% -depth 1 -compress Group4 "$2"
}

SRC_DIR=$(realpath "$1")
DEST_DIR=$(realpath "$2")
date +%r

if [[ -d $SRC_DIR && -d $DEST_DIR ]]; then
	find "$SRC_DIR" -name '*.tif' | while read SRC_FILE; do
		DEST_FILE=${SRC_FILE/${SRC_DIR}/${DEST_DIR}}
		DEST_SUB_DIR=$(dirname "$DEST_FILE")

		if [[ ! -d $DEST_SUB_DIR ]]; then
			mkdir "$DEST_SUB_DIR"
		fi

		#			(
		doConvert "$SRC_FILE" "$DEST_FILE"
		date +%r
		#			) &
	done
else
	echo 'bad dest dir'
fi

echo
