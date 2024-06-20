<?php
/**
 *  XML to Associative Array Class
 *
 *  Usage:
 *     $domObj = new xmlToArrayParser($xml);
 *     $domArr = $domObj->array;
 *
 *     On Success:
 *     eg. $domArr['top']['element2']['attrib']['var2'] => val2
 *
 *     On Error:
 *     eg. Error Code [76] "Mismatched tag", at char 58 on line 3
 */

namespace Library;

use Library\Exception\RuntimeException;

/**
 * Convert an xml file or string to an associative array (including the tag attributes):
 * $domObj = new XmlParser($xml);
 * $elemVal = $domObj->array['element']
 * Or:  $domArr=$domObj->array;  $elemVal = $domArr['element'].
 *
 * @param string $xml file/string.
 *
 * @version  3.0
 */
class XmlParser
{
	/** The array created by the parser can be assigned to any variable: $anyVarArr = $domObj->array.*/
	public $array = [];
	private $parser;
	private $pointer;

	/** Constructor: $domObj = new XmlParser($xml); */
	public function __construct(string $xml)
	{
		$this->pointer =& $this->array;
		$this->parser  = xml_parser_create('UTF-8');

		xml_set_object($this->parser, $this);
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->parser, "tagOpen", "tagClose");
		xml_set_character_data_handler($this->parser, "cdata");

		if (xml_parse($this->parser, ltrim($xml)) === 0) {
			$errCode    = xml_get_error_code($this->parser);
			$errMessage = 'Error Code [' . $errCode . '] "' . xml_error_string($errCode) .
						  '", at char ' . xml_get_current_column_number($this->parser) .
						  ' on line ' . xml_get_current_line_number($this->parser) . '.';
			throw new RuntimeException($errMessage, $errCode);
		}
	}

	public function __destruct()
	{
		xml_parser_free($this->parser);
	}

	private function tagOpen($parser, $tag, $attributes)
	{
		$this->_toArray($tag, 'attrib');
		$idx = $this->_toArray($tag, 'cdata');
		if (isset($idx)) {
			$this->pointer[$tag][$idx] = ['@idx' => $idx, '@parent' => &$this->pointer];
			$this->pointer             =& $this->pointer[$tag][$idx];
		}
		else {
			$this->pointer[$tag] = ['@parent' => &$this->pointer];
			$this->pointer       =& $this->pointer[$tag];
		}
		if (!empty($attributes)) {
			$this->pointer['attrib'] = $attributes;
		}
	}

	/** Adds the current elements content to the current pointer[cdata] array. */
	private function cdata($parser, $cdata)
	{
		if (isset($this->pointer['cdata'])) {
			$this->pointer['cdata'] .= $cdata;
		}
		else {
			$this->pointer['cdata'] = $cdata;
		}
	}

	private function tagClose($parser, $tag)
	{
		$current = &$this->pointer;
		if (isset($this->pointer['@idx'])) {
			unset($current['@idx']);
		}
		$this->pointer = &$this->pointer['@parent'];
		unset($current['@parent']);
		if (isset($current['cdata']) && count($current) == 1) {
			$current = $current['cdata'];
		}
		else {
			if (empty($current['cdata'])) {
				unset($current['cdata']);
			}
		}
	}

	/** Converts a single element item into array(element[0]) if a second element of the same name is encountered. */
	private function _toArray($tag, $item)
	{
		if (isset($this->pointer[$tag]) && !is_array($this->pointer[$tag])) {
			$content             = $this->pointer[$tag];
			$this->pointer[$tag] = [(0) => $content];
			$idx                 = 1;
		}
		else {
			if (isset($this->pointer[$tag])) {
				$idx = count($this->pointer[$tag]);
				if (!isset($this->pointer[$tag][0])) {
					foreach ($this->pointer[$tag] as $key => $value) {
						unset($this->pointer[$tag][$key]);
						$this->pointer[$tag][0][$key] = $value;
					}
				}
			}
			else {
				$idx = null;
			}
		}
		return $idx;
	}
}
