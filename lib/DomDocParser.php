<?php

namespace Library;

use DOMDocument;
use DOMNode;

/**
 *
 */
class DomDocParser extends DOMDocument
{
	/**
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->element2arr($this->documentElement);
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return array
	 */
	private function element2arr(DOMNode $node): array
	{
		$arr = ['tag' => $node->tagName];

		foreach ($node->attributes as $attribute) {
			$arr[$attribute->name] = $attribute->value;
		}

		foreach ($node->childNodes as $childNode) {
			if ($childNode->nodeType == XML_TEXT_NODE) {
				$arr['text'] = trim($childNode->wholeText);
			}
			else {
				$arr['childNodes'][] = $this->element2arr($childNode);
			}
		}

		return $arr;
	}

	/**
	 * @return array
	 */
	public function toTextArray()
	{
		return $this->node2textArr($this->documentElement);
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return array|string
	 */
	private function node2textArr(DOMNode $node): array|string
	{
		$tagName = $node->tagName;

		if (count($node->childNodes) === 1) {
			$childNode = $node->firstChild;

			if ($childNode->nodeType == XML_TEXT_NODE) {
				$arr = trim($childNode->wholeText);
			}
			else {
				$arr = $this->node2textArr($childNode);
			}
		}
		else {
			$arr = [];

			foreach ($node->childNodes as $childNode) {
				if ($childNode->nodeType == XML_TEXT_NODE) {
					$t = trim($childNode->wholeText);

					if (in_array($tagName, ['html', 'head', 'body', 'div', 'span'])) {
						if ($t !== '') {
							$arr[] = $t;
						}
					}
					elseif (!in_array($tagName, ['table', 'tr'])) {
						$arr[] = $t;
					}
				}
				else {
					$arr[] = $this->node2textArr($childNode);
				}
			}
		}

		return $arr;
	}

}
