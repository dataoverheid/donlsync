<?php

namespace DonlSync\Catalog\Source\ODataCatalog\Tools;

use DOMNode;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Helper\XMLParser;

/**
 * Class ODataCatalogXMLMetadataExtractor.
 *
 * Responsible for extracting viable metadata from the XML response received from the ODataCatalog.
 */
class ODataCatalogXMLMetadataExtractor extends XMLParser
{
    /**
     * Searches through the XML for a specific named field. The named field is translated to 1 or
     * more configured XPath queries; these XPath queries will be used to perform the actual search.
     *
     * @param string  $xpath_key The XPath key holding the named field
     * @param string  $field     The named field
     * @param DOMNode $context   The query context
     * @param bool    $multiple  Whether or not to expect an array as output
     *
     * @throws CatalogHarvestingException On a non-existent $xpath_key or $field
     *
     * @return string|string[] The search result
     */
    public function namedField(string $xpath_key, string $field, DOMNode $context = null,
                               bool $multiple = false)
    {
        if (!array_key_exists($xpath_key, $this->xpath_selectors)) {
            throw new CatalogHarvestingException(
                'No such $xpath_key configured; ' . $xpath_key
            );
        }

        if (!array_key_exists($field, $this->xpath_selectors[$xpath_key])) {
            throw new CatalogHarvestingException('No such $field in $xpath_key; ' . $field);
        }

        $method = $multiple
            ? 'queryMultipleValues'
            : 'querySingleValue';

        foreach ($this->xpath_selectors[$xpath_key][$field] as $xpath_selector) {
            $value = call_user_func([$this, $method], $xpath_selector, $context);

            if (!empty($value)) {
                return $value;
            }
        }

        return $multiple ? [] : '';
    }

    /**
     * Searches through the XML for a specific named field. The named field is translated to 1 or
     * more configured XPath queries; these XPath queries will be used to perform the actual search.
     *
     * This method will specifically search for named fields configured under the 'dataset' key.
     *
     * @param string  $field    The named field
     * @param DOMNode $context  The query context
     * @param bool    $multiple Whether or not to expect an array as output
     *
     * @throws CatalogHarvestingException On a non-existent $field
     *
     * @return string|string[] The search result
     *
     * @see ODataCatalogXMLMetadataExtractor::namedField()
     */
    public function datasetField(string $field, DOMNode $context = null, bool $multiple = false)
    {
        return $this->namedField('dataset', $field, $context, $multiple);
    }

    /**
     * Searches through the XML for a specific named field. The named field is translated to 1 or
     * more configured XPath queries; these XPath queries will be used to perform the actual search.
     *
     * This method will specifically search for named fields configured under the 'resource' key.
     *
     * @param string  $field    The named field
     * @param DOMNode $context  The query context
     * @param bool    $multiple Whether or not to expect an array as output
     *
     * @throws CatalogHarvestingException On a non-existent $field
     *
     * @return string|string[] The search result
     *
     * @see ODataCatalogXMLMetadataExtractor::namedField()
     */
    public function resourceField(string $field, DOMNode $context = null, bool $multiple = false)
    {
        return $this->namedField('resource', $field, $context, $multiple);
    }
}
