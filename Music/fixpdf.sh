#!/usr/bin/env bash

if  [ $# -le 1 ]; then
	echo "need input"
	exit
fi

if  [ ! -e "$1" ]; then
	echo "bad input file or dir"
	exit
fi

if  [ ! -d "$2" ]; then
	echo "bad dest dir"
	exit
fi

#THIS_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)

function kill_subprocesses() {
	#  local PID
	#  for PID in $CHILD_PID; do
	#    if [[$(kill -0 $PID) < = /dev/null]]; then
	#      kill -SIGKILL $PID
	#    fi
	#  done
	pkill -P $$
	printf '\n'
	exit
}

trap 'kill_subprocesses' SIGHUP SIGINT SIGTERM SIGQUIT SIGTSTP SIGSTOP

# Working resolution. "7.75" is the screen width of a 13-inch iPad.
RESO=600
FINALRES=$(bc -e "7.75 * $RESO")

function doConvert() {
	magick -density $RESOx$RESO -units pixelsperinch "$1" \
  -alpha off -colorspace gray \
  -normalize \
  -despeckle \
  -blur 0x0.7 \
  -threshold 50% \
  -background white -deskew 80% \
  -trim +repage \
  -bordercolor white -border 16x8 \
  -adaptive-resize $FINALRES \
  -blur 0x0.9 \
  -threshold 50% -depth 1 \
  -compress Group4 \
  "$2"
}

# Source can be a file or directory.
SRC="$1"

# Destination must be a directory.
DEST_DIR="$2"

echo -n "Started "
date +%r

if [[ -f $SRC ]]; then
	BNAME="$(basename "$SRC")"
	time doConvert "$SRC" "$DEST_DIR/$BNAME"
  echo
  echo -n "Finnished "
	date +%r
elif [[ -d $SRC ]]; then
#  set MAGICK_THREAD_LIMIT=1
	find "$SRC" -iname '*.pdf' | while read file; do
		BNAME="$(basename "$file")"
		(
			doConvert "$file" "$DEST_DIR/$BNAME"
			echo -n "Finnished $BNAME "
			date +%r
		) &
		sleep 1
	done
else
	echo "Input error."
fi

wait
echo
