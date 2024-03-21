<?php

namespace Library;

/**
 * Behaves like var_export() but uses "[]" notation instead of "array()".
 *
 * @param $in
 * @param bool $return false
 * @param string $indentStr '   ' (three spaces)
 * @return string
 */
function var_export($in, bool $return = false, $indentStr = '   '): string
{
	static $indent = 0;

	switch (gettype($in)) {
		case 'array':
			$returnStr = "[\n";
			$indent++;
			foreach ($in as $k => $v) {
				if (!is_resource($v)) {
					if (!is_integer($k)) {
						$k = "'$k'";
					}

					$returnStr .= str_repeat($indentStr, $indent) . $k . ' => ' . var_export($v) . ",\n";
				}
			}
			$returnStr .= str_repeat($indentStr, --$indent) . ']';
			break;

		case 'object':
			$returnStr = str_repeat($indentStr, $indent++) . "{\n";
			foreach ($in as $k => $v) {
				if (!is_resource($v)) {
					$returnStr .= str_repeat($indentStr, $indent) . "'$k' => " . var_export($v) . ",\n";
				}
			}
			$returnStr .= str_repeat($indentStr, --$indent) . '}';
			break;

		case 'resource':
			$returnStr = '';
			break;

		default:
			$returnStr = \var_export($in, true);	//	Uses built-in function.
	}

	if ($return) {
		return $returnStr;
	}

	echo $returnStr, PHP_EOL;
	return '';
}
