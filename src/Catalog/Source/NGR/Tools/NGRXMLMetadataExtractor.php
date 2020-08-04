<?php

namespace DonlSync\Catalog\Source\NGR\Tools;

use DOMElement;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Helper\XMLParser;

/**
 * Class NGRXMLMetadataExtractor.
 *
 * Responsible for extracting viable metadata from the XML response received from the NGR.
 */
class NGRXMLMetadataExtractor extends XMLParser
{
    /**
     * Queries the XML response for a given named field. The named field is translated to a XPath
     * selector which will be used to perform the actual query.
     *
     * @param string $field    The named field to look for
     * @param int    $iterator The nth dataset to search through
     * @param bool   $multiple (optional) Whether or not to expect multiple return values
     *
     * @throws CatalogHarvestingException If no field is configured with the given name
     *
     * @return string|string[] The search result
     */
    public function datasetField(string $field, int $iterator, bool $multiple = false)
    {
        if (!array_key_exists($field, $this->xpath_selectors['dataset'])) {
            throw new CatalogHarvestingException(
                'Request for dataset field without XPath selector; field ' . $field
            );
        }

        $method = $multiple
            ? 'queryMultipleValues'
            : 'querySingleValue';

        foreach ($this->xpath_selectors['dataset'][$field] as $xpath_selector) {
            $value = $this->$method(sprintf($xpath_selector, $iterator), null);

            if (!empty($value)) {
                return $value;
            }
        }

        return $multiple ? [] : '';
    }

    /**
     * Queries the XML response for a given named field. The named field is translated to a XPath
     * selector which will be used to perform the actual query.
     *
     * When the XPath selector for the field begins with the character `/`, the $context argument
     * will be ignored and lookup will be executed without the DOMElement as context.
     *
     * @param string          $field    The named field to look for
     * @param DOMElement|null $context  (optional) The DOMElement to use as context while searching
     * @param int             $iterator (optional) The nth dataset to search through
     * @param bool            $multiple (optional) Whether or not to expect multiple return values
     *
     * @throws CatalogHarvestingException If no field is configured with the given name
     *
     * @return string|string[] The search result
     */
    public function resourceField(string $field, DOMElement $context = null, int $iterator = -1,
                                  bool $multiple = false)
    {
        if (!array_key_exists($field, $this->xpath_selectors['resource'])) {
            throw new CatalogHarvestingException(
                'Request for resource field without XPath selector; field ' . $field
            );
        }

        $method = $multiple
            ? 'queryMultipleValues'
            : 'querySingleValue';

        foreach ($this->xpath_selectors['resource'][$field] as $xpath_selector) {
            $value = ('/' === mb_substr($xpath_selector, 0, 1))
                ? $this->$method(sprintf($xpath_selector, $iterator), $context)
                : $this->$method($xpath_selector, $context);

            if (!empty($value)) {
                return $value;
            }
        }

        return $multiple ? [] : '';
    }

    /**
     * Queries the XML for the dataset modificationDate.
     *
     * @param int $iterator The nth dataset to search through
     *
     * @return string The search result
     */
    public function getModificationDate(int $iterator): string
    {
        $xpaths = $this->xpath_selectors['dataset']['modificationDate'];
        $value  = $this->queryMultipleValues(sprintf($xpaths[0], $iterator));

        if (count($value) > 0) {
            $value = $value[count($value) - 1];
        }

        if ([] === $value) {
            $value = $this->querySingleValue(sprintf($xpaths[1], $iterator));
        }

        if ('' === $value) {
            $value = $this->querySingleValue(sprintf($xpaths[2], $iterator));
        }

        return $value;
    }

    /**
     * Queries the XML for the dataset license.
     *
     * @param int $i The nth dataset to search through
     *
     * @return string The search result
     */
    public function getDatasetLicense(int $i): string
    {
        $xpaths   = $this->xpath_selectors['dataset']['license'];
        $licenses = [];

        foreach ($xpaths as $xpath) {
            $licenses = array_merge($licenses, $this->queryMultipleValues(sprintf($xpath, $i)));
        }

        foreach ($licenses as $license) {
            if (filter_var($license, FILTER_VALIDATE_URL)) {
                return $license;
            }
        }

        foreach ($licenses as $license) {
            if (!empty($license)) {
                return $license;
            }
        }

        return '';
    }

    /**
     * Queries the XML for the dataset contactPoint name.
     *
     * @param int $iterator The nth dataset to search through
     *
     * @return string The search result
     */
    public function getContactPointName($iterator): string
    {
        $xpaths = $this->xpath_selectors['dataset']['contact_point_name'];
        $person = $this->querySingleValue(sprintf($xpaths[0], $iterator));
        $org    = $this->querySingleValue(sprintf($xpaths[1], $iterator));

        if ('' !== $person && '' === $org) {
            return $person;
        }

        if ('' !== $person && '' !== $org) {
            return $person . ', ' . $org;
        }

        if ('' === $org) {
            return $this->querySingleValue(sprintf($xpaths[2], $iterator));
        }

        return $org;
    }
}
