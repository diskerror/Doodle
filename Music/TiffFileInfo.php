<?php

namespace Music;

use RuntimeException;

class TiffFileInfo
{
    public readonly string  $name;
    public readonly int     $frameCount;
    public readonly array   $widths;         // Array of integers
    public readonly array   $resolutions;    // Array of numbers
    public readonly int     $largestFrame;
    public readonly string  $nameFrame;

    /**
     * TiffFileInfo constructor.
     * Finding the largest frame might not be necessary. The TIFF spec says that it not required
     *   that the reader go beyond the first frame.
     *
     * @param string $fname
     */
    public function __construct(string $fname)
    {
        if (!is_file($fname)) {
            echo $fname, PHP_EOL;
            throw new RuntimeException('Not a file.' . PHP_EOL . '  ' . $fname);
        }
        $pathinfo  = pathinfo($fname);
        $extension = strtolower((string)$pathinfo['extension']);
        if ($extension !== 'tif' && $extension !== 'tiff') {
            throw new RuntimeException('Not a TIFF file.' . PHP_EOL . '  ' . $fname);
        }

        $this->name = $fname;
        $escName    = escapeshellarg($fname);

        //  PDF: pdfimages -list (flattened table)
        //  TIFF: magick identify -format '%w ' (numbers with spaces)
        //  TIFF: tiffinfo (text block for each frame)
        //  "exiftool -j -struct -g " only gives the largest frame

        $this->widths      = explode(' ', exec('magick identify -format "%w " ' . $escName));
        $this->frameCount  = count($this->widths);

        $testSize     = $this->widths[0];
        $largestFrame = 0;
        if ($this->frameCount > 1) {
            // loop through images and find the largest
            for ($i = 1; $i < $this->frameCount; $i++) {
                if ($this->widths[$i] > $testSize) {
                    $testSize     = $this->widths[$i];
                    $largestFrame = $i;
                }
            }
        }
        $this->largestFrame = $largestFrame;

        $this->nameFrame = $escName . ($this->frameCount > 1 ? '[' . $this->largestFrame . ']' : '');
    }

    public function __get(string $name)
    {
        switch ($name) {
            case 'width':
                return (int)$this->widths[$this->largestFrame];

            default:
                throw new RuntimeException("Cannot get property: $name");
        }
    }
}
