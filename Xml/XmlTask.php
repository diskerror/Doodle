<?php

namespace Xml;

use Application\TaskMaster;
use CFPropertyList\CFPropertyList;
use Library\StdIo;
use Library\XmlParser;

class XmlTask extends TaskMaster
{
    function ToJsonAction(string $fileName)
    {
        mb_internal_encoding('UTF-8');
//		ini_set('memory_limit', 0);

        $xmlText   = file_get_contents($fileName);
        $xmlObject = new XmlParser($xmlText);
        StdIo::jsonOut($xmlObject->array);
    }

    function ToPhpAction(string $fileName)
    {
        mb_internal_encoding('UTF-8');
//		ini_set('memory_limit', 0);

        $xmlText   = file_get_contents($fileName);
        $xmlObject = new XmlParser($xmlText);
        StdIo::phpOut($xmlObject->array['TEI.2']['text']);
//        foreach ($xmlObject->array as $key => $value) {
//            StdIo::outln('  ' . $key);
//            foreach ($value as $k => $v) {
//                StdIo::outln('  ' . $k);
//            }
//  TEI.2
//  attrib
//  cdata
//  teiHeader
//  text

//        }
    }

    function PListToJsonAction(string $fileName)
    {
        mb_internal_encoding('UTF-8');

        $plist = new CFPropertyList($fileName);

        StdIo::phpOut($plist->toArray());
    }
}
