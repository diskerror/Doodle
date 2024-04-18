<?php

namespace Service;

/**
 * Copyright (c) 2011 Reid Woodbury.
 *
 * Description:
 *    This class might be too specific. It was created to fix problems in a known data set.
 * Parameters:
 * Usage:
 * Revision History:
 * new 2011-07-08 Reid Woodbury
 */
class Xml2Array
{
	/**
	 * Convert XML data structure string to an equivalent nested associative array.
	 *
	 * @param string $xmlString
	 * @param string $namespaceAction -OPTIONAL
	 */
	public static function process(string $xmlString, string $namespaceAction = '')
	{
		$xmlString = preg_replace('/\r\n/', "\n", $xmlString);

		//	Both of these remove the namespace references at the start of the string.
		switch ($namespaceAction) {
			//	Remove characters up to and including the colon on field names.
			case 'remove':
				$xmlString = preg_replace('/ xmlns.*?>/', '>', $xmlString);
				$xmlString = preg_replace('|(</?).+?:|', '$1', $xmlString);

				//	may need to expand this to cover other attributes.
				$xmlString = preg_replace('/ [a-zA-Z]+:nil=/', ' nil=', $xmlString);
			break;

			//	Change the colon to an underscore.
			case 'incorp':
			case 'incorporate':
				$xmlString = preg_replace('/ xmlns.*?>/', '>', $xmlString);
				$xmlString = preg_replace('/([a-zA-Z]+):/', '${1}_', $xmlString);    //	this is bad
			break;
		}

		// Load the XML formatted string into a Simple XML Element object.
		$simpleXmlElementObject = simplexml_load_string($xmlString);

		return self::_fixArray($simpleXmlElementObject);
	}

	//	Fixes arrays from XML files by:
	//		insuring single element arrays are always arrays;
	//		removes redundant parent so that (ie)
	//		"File.Forms.Form" or "File.Forms.Form[]" becomes "File.Form[]"
	protected static function _fixArray($xmlElement)
	{
		if (is_array($xmlElement)) {
			foreach ($xmlElement as $k => $v) {
				$xmlElement[$k] = self::_fixArray($v);
			}
		}
		elseif (is_object($xmlElement)) {
			$xmlElement = get_object_vars($xmlElement);

			foreach ($xmlElement as $pName => $val) {
				//	find non-empty objects having a name ending with an 's'
				$singular = [];
				if (is_object($val) &&
					preg_match('/^(.*)s$/', $pName, $singular) &&
					property_exists($val, $singular[1])) {
					$sName = $singular[1];

					$xmlElement[$sName] = [];
					foreach ($val->$sName as $vn) {
						$xmlElement[$sName][] = self::_fixArray($vn);
					}

					unset($xmlElement[$pName]);
				}
				else {
					$xmlElement[$pName] = self::_fixArray($val);
				}
			}
		}

		return $xmlElement;
	}
}
