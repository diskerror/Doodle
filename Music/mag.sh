#!env zsh

cd $1

# for f in *.tif; do
#   tv="$(magick identify -format "%z %r" "$f")";
#   echo "$f .${tv}.";
#   if [[ ${tv:0:2} -gt 8 || ${tv:-3:3} == 'RGB' ]]; then
#     magick "$f" -colorspace gray -depth 8 "$f"
#   fi
# done

# IMSLP pre trimmed PDFs
#   -shave 0x2 \

magick *.tif \
  -background white \
  -shave 0x2 \
  -define trim:edges=north,south \
  -trim +repage \
  -bordercolor white \
  -border 0.2% \
  -threshold 50% \
  -depth 1 \
  -compress Group4 \
  -density 200 \
  output.pdf

