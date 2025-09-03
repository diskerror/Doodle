<?php

namespace Music;

use Application\TaskMaster;
use Diskerror\PdfParser\Parser;
use Library\StdIo;
use function Library\escapeshellarg;

class FixPdfTask extends TaskMaster
{
    /**
     * mainAction
     * This takes a list of input PDF files, fixes them,
     *      and the repaired copy to the output directory.
     *
     * @param ...$params
     * @return void
     */
    public function mainAction(...$params): void
    {
        if (count($params) < 2) {
            StdIo::outln('needs file[s] and output directory');
            $this->helpAction();
            return;
        }

        $outputDir = array_pop($params);
        if (!is_dir($outputDir)) {
            throw new RuntimeException('output dir ' . $outputDir . ' does not exist');
        }

        ini_set('memory_limit', -1);
        $exec = $this->inputParams->print ? 'Library\StdIo::outln' : 'exec';

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

            $inputFile  = escapeshellarg($file);
            $outputFile = escapeshellarg($outputDir . '/' . basename($file));

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

    public function structAction(...$params): void
    {
        $this->logger->info('FixPdfTask structAction');

        if (count($params) !== 1) {
            StdIo::outln('One at a time please. Output is large.');
            $this->helpAction();
            return;
        }

        ini_set('memory_limit', -1);

        $pdf      = (new Parser())->parseFile($params[0]);
        $fileInfo = $this->getAllPdf($pdf);
        // 'metadata', 'dictionary', 'details', 'objects', 'pages', 'trailer'
//        foreach ($pdf as $key => $object) {
//            if (is_a($object, PdfObject::class)) {
//                $fileInfo[$key . '_'] = $object->getDetails();
//            }
//            else {
//                $fileInfo[$key . '_'] = $object;
//            }
//
//        }
//        foreach ($pdf->getPages() as $key => $page) {
//            $fileInfo[$key . '_'] = array_merge($page->getDetails(),
//                                                ['objects' => [], 'xobjects' => [], 'cropRes' => []]);
//            foreach ($page->getXObjects() as $k => $v) {
//                $fileInfo[$key . '_']['xobjects'][$k] = $v->getDetails();
//            }
//            $fileInfo[$key . '_']['cropRes']    =
//                array_map(
//                    function ($v) {
//                        return round($v * 9.5, 2);
//                    }, $fileInfo[$key . '_']['CropBox']);
//            $fileInfo[$key . '_']['cropRes'][2] -= $fileInfo[$key . '_']['cropRes'][0];
//            $fileInfo[$key . '_']['cropRes'][3] -= $fileInfo[$key . '_']['cropRes'][1];
//        }
        StdIo::phpOut($fileInfo);
    }

    protected function getAllPdf(iterable $pdfObject): array
    {
        $retrunvalue = [];

        foreach ($pdfObject as $k => $v) {
            if (is_iterable($v)) {
                $retrunvalue[$k] = $this->getAllPdf($v);
                if (is_int($k) && !array_is_list($retrunvalue[$k])) {
                    $retrunvalue[$k] = array_merge(['_i' => $k], $retrunvalue[$k]);
                }
            }
            else {
                $retrunvalue[$k] = $v;
            }
        }

        return $retrunvalue;
    }

}
