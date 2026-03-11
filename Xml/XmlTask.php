<?php

namespace Xml;

use Application\TaskMaster;
use CFPropertyList\CFPropertyList;
use Library\StdIo;
use Library\XmlParser;

class XmlTask extends TaskMaster
{
	/**
	 * Convert XML to JSON.
	 *
	 * @param string $fileName
	 * @return void
	 * @throws \JsonException
	 */
	function ToJsonAction(string $fileName)
    {
        mb_internal_encoding('UTF-8');
//		ini_set('memory_limit', 0);

        $xmlText   = file_get_contents($fileName);
        $xmlObject = new XmlParser($xmlText);
        StdIo::jsonOut($xmlObject->array);
    }

	/**
	 * Convert XML to PHP in var_export() form.
	 *
	 * @param string $fileName
	 * @return void
	 */
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

	/**
	 * Converts plist style XML to JSON.
	 *
	 * @param string $fileName
	 * @return void
	 * @throws \CFPropertyList\IOException
	 */
	function PListToJsonAction(string $fileName)
    {
        mb_internal_encoding('UTF-8');

        $plist = new CFPropertyList($fileName);

        StdIo::phpOut($plist->toArray());
    }
}
