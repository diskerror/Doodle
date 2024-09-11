#!/usr/bin/env bash

# Convert to 1-bit file.
# convert in.tif -monochrome -depth 1 out.tif

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

THIS_DIR=$(dirname $0)

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

# Input resolution or density.
RES=800

# Total width in pixels.
RESZ=$(bc -e "7.75 * $RES")

# Final resolution in pixels.
RESF=480

function doConvert() {
	#    convert -density 600x600 -units pixelsperinch "$1" \
	#	-alpha off -threshold 50% -depth 1 -compress Group4 "$2"

	#  magick -density ${RES}x${RES} -units pixelsperinch "$1" \
	#    -alpha off -threshold 50% -depth 1 -compress Group4 "$2"

	nice magick -density ${RES}x${RES} -units pixelsperinch "$1" \
		-alpha off -blur 3x0.9 \
		-threshold 68% \
		-background white -deskew 80% \
		-adaptive-resize $RESZ -resample ${RESF}x${RESF} \
		-trim +repage -bordercolor white -border 5x2 \
		-threshold 50% -depth 1 \
		-compress Group4 \
		"$2"

	#     convert -density 480x480 -units pixelsperinch "$1" \
	#     	-alpha off -blur 2x0.7 -threshold 70% -depth 1 -compress Group4 "$2"

	#     convert -density 480x480 -units pixelsperinch "$1" \
	#     	-alpha off -threshold 50% -depth 1 -compress Group4 "$2"
}

# Source can be a file or directory.
SRC="$1"

# Destination must be a directory.
DEST_DIR="$2"

date +%r

if [[ -f $SRC && -d $DEST_DIR ]]; then
	BNAME="$(basename "$SRC")"
	time doConvert "$SRC" "$DEST_DIR/$BNAME"
	"$THIS_DIR/Apply.php" "$DEST_DIR/$BNAME"
elif [[ -d $SRC && -d $DEST_DIR ]]; then
	find "$SRC" -iname '*.pdf' | while read file; do
		BNAME="$(basename "$file")"
		(
			doConvert "$file" "$DEST_DIR/$BNAME"
			"$THIS_DIR/Apply.php" "$DEST_DIR/$BNAME"
			date +%r
		) &
	done
else
	echo "Input error."
fi

echo
