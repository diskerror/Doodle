#!/usr/bin/env bash

if  [ $# -le 1 ]; then
	echo "need input"
	exit
fi

if  [ ! -f "$1" ]; then
	echo "bad input file or dir"
	exit
fi

declare FILES=''

for f in "$@"; do
  FILES="$FILES ${f// /\\ }"
done

# Working resolution. "7.75" is the screen width of a 13-inch iPad.
RESO=600
FINALRES=$(bc -e "7.75 * $RESO")

cmd="magick -density $RESOx$RESO -units pixelsperinch \
	$FILES \
  -background white -deskew 80% \
  -trim +repage \
  -bordercolor white -border 16x8 \
  -adaptive-resize $FINALRES \
  -blur 0x1 \
  -threshold 50% -depth 1 \
  -compress Group4 \
  \"$1.pdf\""

eval $cmd
