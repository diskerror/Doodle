#!/usr/bin/env bash

# BuildPdfTask.sh
#
# Replicates the functionality of BuildPdfTask.php using a shell script and ImageMagick.
#
# Usage: ./build_pdf.sh [options] <image-file> ... <destination-pdf>
# Options:
#   -r, --resolution <number>  Set output PDF density (default: 600)
#   -b, --blank                Add a blank page at the beginning
#
# Dependencies: ImageMagick (magick), bc (for floating point math)

set -e

# Default values
RESOLUTION=600
ADD_BLANK=false

# Parse options
# We use a while loop to handle options before positional arguments
while [[ "$1" =~ ^- ]]; do
    case "$1" in
        -r|--resolution)
            if [[ -n "$2" && ! "$2" =~ ^- ]]; then
                RESOLUTION="$2"
                shift 2
            else
                echo "Error: --resolution requires a number argument."
                exit 1
            fi
            ;;
        -b|--blank)
            ADD_BLANK=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Check arguments
if [ "$#" -lt 2 ]; then
    echo "Usage: $0 [options] <image-file> ... <destination-pdf>"
    exit 1
fi

# Extract arguments
args=("$@")
num_args=${#args[@]}
dest_index=$((num_args - 1))
dest_file="${args[$dest_index]}"
# Input files are all arguments except the last one
input_files=("${args[@]:0:$dest_index}")

# Setup destination directory
dest_dir=$(dirname "$dest_file")
dest_base=$(basename "$dest_file")
# Ensure extension is .pdf
if [[ "${dest_base,,}" != *.pdf ]]; then
    dest_base="${dest_base}.pdf"
fi
dest_path="$dest_dir/$dest_base"

if [ ! -d "$dest_dir" ]; then
    mkdir -p "$dest_dir"
fi

# Get absolute path for destination because we will change directory
if command -v realpath >/dev/null 2>&1; then
    dest_path=$(realpath "$dest_path")
else
    # Fallback for absolute path
    dest_path="$(cd "$(dirname "$dest_path")"; pwd)/$(basename "$dest_path")"
fi

# The PHP script changes directory to the directory of the first input file
# to avoid issues with spaces in parent directories in ImageMagick.
first_input="${input_files[0]}"
work_dir=$(dirname "$first_input")

# Store script directory to find blank.pdf later if needed
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

echo "Changing working directory to: $work_dir"
cd "$work_dir" || exit 1

# Re-evaluate input files relative to the new working directory (basenames)
input_filenames=()
for f in "${input_files[@]}"; do
    input_filenames+=("$(basename "$f")")
done

tmp_suffix="_TMP.tif"
tmp_files=()

# Generate temporary filenames
for f in "${input_filenames[@]}"; do
    filename=$(basename "$f")
    # Remove extension
    filename_no_ext="${filename%.*}"
    tmp_files+=("${filename_no_ext}${tmp_suffix}")
done

# Parallel execution limit
MAX_JOBS=10
# export OMP_NUM_THREADS=1

# Function to wait for jobs
wait_for_jobs() {
    while [ $(jobs -r | wc -l) -ge $MAX_JOBS ]; do
        sleep 0.01
    done
}
# Gemini suggests:
# export OMP_NUM_THREADS=1
# MAX_JOBS=10

start_time=$(date +%s)

# 1. Deskew and Trim (Combined Loop)
echo "Deskewing and Trimming..."

frac=0.004
# Calculate size: (frac * 2.0) + 1.0
size=$(echo "($frac * 2.0) + 1.0" | bc -l)

for i in "${!input_filenames[@]}"; do
    infile="${input_filenames[$i]}"
    outfile="${tmp_files[$i]}"

    (
        # --- Deskew ---
        # Get resolution
        resolution=$(magick identify -format "%x" "$infile" 2>/dev/null || echo "0")
        # Extract number (handle "300 PixelsPerInch")
        res_val=$(echo "$resolution" | grep -oE '[0-9.]+' | head -1)
        if [ -z "$res_val" ]; then res_val=0; fi

        resize_opts=""
        # Check if resolution > 600
        if (( $(echo "$res_val > 600" | bc -l 2>/dev/null || echo 0) )); then
             factor=$(echo "60000.0 / $res_val" | bc -l)
             resize_opts="-adaptive-resize ${factor}%"
        fi

        # Deskew and save to temp file
        magick "$infile" \
            -alpha off -colorspace gray -depth 8 \
            -despeckle \
            $resize_opts \
            -virtual-pixel white -background white \
            -deskew 80% +repage \
            -set filename:fname "%t${tmp_suffix}" \
            '%[filename:fname]'

        # --- Trim ---
        # Calculate crop geometry using a sub-shell magick command on the deskewed file
        crop_geom=$(magick "$outfile" -virtual-pixel white \
          -blur 0x"%[fx:round(w*0.004)]" -fuzz 4% \
          -define trim:percent-background=99% -trim \
          -format \
          "%[fx:round(w*$size)]x%[fx:round(h*$size)]+%[fx:round(page.x-(w*$frac))]+%[fx:round(page.y-(h*$frac))]" \
          info:)

        # Apply crop to the same file
        magick "$outfile" \
            -virtual-pixel white -background white \
            -crop "$crop_geom" \
            +repage \
            -set filename:fname "%f" \
            '%[filename:fname]'
    ) &

    wait_for_jobs
done
wait

# 2. Average Width
echo "Finding average width..."
total_width=0
count=0
for f in "${tmp_files[@]}"; do
    w=$(magick identify -format "%w" "$f")
    total_width=$((total_width + w))
    count=$((count + 1))
done

if [ "$count" -gt 0 ]; then
    avg_width=$((total_width / count))
else
    avg_width=0
fi
echo "Average width: $avg_width"

# 3. Resize and Combine (Combined Step)
echo "Resizing, Combining and Compressing..."

# Prepare input list
combine_inputs=()

# Add blank page if requested
if [ "$ADD_BLANK" = true ]; then
    # We can generate a blank page on the fly with ImageMagick
    # Size should match the target width and have a reasonable aspect ratio (e.g., A4 or Letter)
    # However, since we are resizing everything to avg_width, we can just create a canvas of that width.
    # Assuming a standard aspect ratio of roughly 1.414 (A4) or 1.29 (Letter).
    # Let's use the height of the first image as a heuristic or just a standard ratio.
    # But the user specifically mentioned using @/Music/blank.pdf as source.

    BLANK_FILE="$SCRIPT_DIR/blank.pdf"
    if [ -f "$BLANK_FILE" ]; then
        echo "Adding blank page from $BLANK_FILE"
        combine_inputs+=("$BLANK_FILE")
    else
        echo "Warning: Blank file $BLANK_FILE not found. Skipping blank page."
    fi
fi

combine_inputs+=("${tmp_files[@]}")

# Combine images
# Note: If using a PDF as input (blank.pdf), adaptive-resize might behave differently on it if it's vector.
# But usually ImageMagick rasterizes PDF inputs.
# To be safe and consistent with the PHP script which just prepended the file path:
# The PHP script did: magick {$blankOpt}*$allTempFiles ...
# So it just prepended the file.

magick "${combine_inputs[@]}" \
    -adaptive-resize "${avg_width}" \
    -threshold 60% -depth 1 \
    -compress Group4 \
    -density "$RESOLUTION" -units pixelsperinch \
    "$dest_path"

# 4. Cleanup
echo "Cleaning up temporary files..."
rm "${tmp_files[@]}"

end_time=$(date +%s)
duration=$((end_time - start_time))
printf "Total runtime: %02d:%02d:%02d\n" $((duration/3600)) $((duration%3600/60)) $((duration%60))
