<?php

namespace Music;

use RuntimeException;

class ImageFileInfo
{
    public readonly string $name;
    public readonly string $escName;
    public readonly int    $frameCount;
    public readonly array  $widths;         // Array of integers
    private array          $resolutions;    // Array of numbers
    public readonly int    $largestFrame;
    public readonly string $nameFrame;

    public function __construct(string $fname)
    {
        if (!is_file($fname)) {
            echo $fname, PHP_EOL;
            throw new RuntimeException('Not a file.' . PHP_EOL . '  ' . $fname);
        }
        $this->name    = $fname;
        $this->escName = escapeshellarg($fname);

        $this->widths     = explode(' ', exec('magick identify -format "%w " ' . $this->escName));
        $this->frameCount = count($this->widths);

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

        $this->nameFrame = $this->escName . ($this->frameCount > 1 ? '[' . $this->largestFrame . ']' : '');
    }

    public function __get(string $name)
    {
        switch ($name) {
            case 'width':
                return (int)$this->widths[$this->largestFrame];

            case 'resolutions':
                $this->getResolutions();
                return $this->resolutions;

            case 'resolution':
                $this->getResolutions();
                return (int)$this->resolutions[$this->largestFrame];

            default:
                throw new RuntimeException("Cannot get property: $name");
        }
    }

    private function getResolutions(): void
    {
        if (!isset($this->resolutions)) {
            $this->resolutions = explode(' ', exec('magick identify -format "%x " ' . $this->escName));
        }
    }
}
