<?php
/**
 * bPost Signature class
 *
 * @author    Serge Jamasb <serge@stigmi.eu>
 * @version   3.0.0
 * @copyright Copyright (c), Eontech.net. All rights reserved.
 * @license   BSD License
 */

class EontechModBpostOrderBoxOptionSigned extends EontechModBpostOrderBoxOption
{
	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @param  \DomDocument $document
	 * @param  string	   $prefix
	 * @return \DomElement
	 */
	public function toXML(\DOMDocument $document, $prefix = 'common')
	{
		$tag_name = 'signed';
		if ($prefix !== null)
			$tag_name = $prefix.':'.$tag_name;

		return $document->createElement($tag_name);
	}

	/**
	 * @param  \SimpleXMLElement $xml
	 * @return Signed
	 */
	public static function createFromXML(\SimpleXMLElement $xml)
	{
		return new EontechModBpostOrderBoxOptionSigned();
	}
}
