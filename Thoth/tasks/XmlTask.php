<?php

use CFPropertyList\CFPropertyList;
use Service\StdIo;
use Service\XmlParser;

class XmlTask extends TaskMaster
{
	function XmlToJsonAction(string $fileName)
	{
		mb_internal_encoding('UTF-8');
//		ini_set('memory_limit', 100000000000);

		$xmlText   = file_get_contents($fileName);
		$xmlObject = new XmlParser($xmlText);
		StdIo::phpOut($xmlObject->array);
	}

	function PListToJsonAction(string $fileName)
	{
		mb_internal_encoding('UTF-8');

		$plist = new CFPropertyList($fileName);

		StdIo::phpOut($plist->toArray());
	}
}
