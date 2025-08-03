<?php

namespace Music;

use Application\TaskMaster;
use Smalot\PdfParser\Parser;

class FixPdfTask extends TaskMaster
{
    function mainAction(...$params)
    {
//        if (count($params) < 2) {
//            StdIo::outln('needs file[s] and output directory');
//            $this->helpAction();
//            return;
//        }

        $outputDir = array_pop($params);
//        if (!is_dir($outputDir)) {
//            throw new RuntimeException('output dir ' . $outputDir . ' does not exist');
//        }

        ini_set('memory_limit', -1);
        $exec = $this->inputParams->print ? 'Library\StdIo::outln' : 'exec';

        $escArr = [
            ' ' => '\\ ',
            "'" => '\\\'',
            '(' => '\\(',
            ')' => '\\)',
            '[' => '\\[',
            ']' => '\\]',
            '{' => '\\{',
            '}' => '\\}',
            '*' => '\\*',
            '?' => '\\?',
            '\\' => '\\\\'
        ];

        foreach ($params as $file) {
            $pdf      = (new Parser())->parseFile($file);
            $fileInfo = [];
            foreach ($pdf->getObjects() as $key => $object) {
                $details = $object->getDetails();
                //  Hopefully 'page' always comes before 'xobject'
                if (key_exists('Type', $details)) {
                    switch ($details['Type']) {
                        case 'Page':
                            $page = $details;
                            break;
                        case 'XObject':
                            $fileInfo[] = ['page' => $page, 'xobject' => $details];
                            break;
                        default:
                            break;
                    }
                }
            }
            unset($pdf);

            //  We do some conversions of points to inches and pixels
            for ($f = 0; $f < count($fileInfo); $f++) {
                //  Page dimensions are now in inches
                $fileInfo[$f]['page_width']  = $fileInfo[$f]['page']['MediaBox'][2] / 72.0;
                $fileInfo[$f]['page_height'] = $fileInfo[$f]['page']['MediaBox'][3] / 72.0;

                //  Resolution is now in pixels per inch
                $fileInfo[$f]['resolution'] =
                    round($fileInfo[$f]['xobject']['Width'] / $fileInfo[$f]['page_width']);

                //  Crop box is now in pixels
                $fileInfo[$f]['crop_box'] = [];
                foreach ($fileInfo[$f]['page']['CropBox'] as $value) {
                    $fileInfo[$f]['crop_box'][] = round(($value / 72.0) * $fileInfo[$f]['resolution']);
                }
            }

            $inputFile  = strtr($file, $escArr);
            $outputFile = strtr($outputDir . '/XXXX' . basename($file), $escArr);

            $cmdArray = [];
            $cmd      = "
magick -density {$fileInfo[0]['resolution']} -units pixelsperinch \
  {$inputFile} \
  -alpha off -colorspace gray \
  -despeckle \
  -background white \
  -deskew 80% \
  -blur 0x1.1 \
  -threshold 50% \
  -define trim:percent-background=99.1% \
  -trim +repage \
  -bordercolor white -border 0.2% \
  -depth 1 \
  -compress Group4 \
  {$outputFile}
";

            call_user_func($exec, $cmd);
        }

    }

}
