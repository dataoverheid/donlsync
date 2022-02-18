<?php

namespace DonlSync\Helper;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use DonlSync\Configuration;
use DonlSync\Exception\DonlSyncRuntimeException;

/**
 * Class XMLParser.
 *
 * Enables searching through XML contents for specific data based on named field and/or specific
 * XPath queries.
 */
class XMLParser
{
    /**
     * The object for traversing a XML document.
     */
    protected DOMXPath $xpath;

    /**
     * A `{key} => {value}` array of XPath selectors for a given DCAT property.
     *
     * @var array<string, array>
     */
    protected array $xpath_selectors;

    /**
     * XMLParser constructor.
     *
     * @param string               $xml             The XML to harvest data from
     * @param array<string, array> $xpath_selectors The XPath selectors to query for named fields
     */
    public function __construct(string $xml, array $xpath_selectors)
    {
        Configuration::checkKeys($xpath_selectors, ['namespaces']);

        libxml_use_internal_errors(true);

        $xml_document = new DOMDocument();

        if (empty($xml) || !$xml_document->loadXML($xml)) {
            throw new DonlSyncRuntimeException('Invalid XML provided');
        }

        $this->xpath           = new DOMXPath($xml_document);
        $this->xpath_selectors = $xpath_selectors;

        $this->registerNamespaces($xpath_selectors['namespaces']);
    }

    /**
     * Wrapper for `DOMXPath::query()`.
     *
     * @param string       $query The query to execute
     * @param DOMNode|null $node  The contextual node
     *
     * @return DOMNodeList<DOMNode>|bool The search results
     */
    public function query(string $query, DOMNode $node = null)
    {
        return $this->xpath->query($query, $node);
    }

    /**
     * Queries for a single value in the document. If the query has more than one hit, only the
     * first hit will be returned.
     *
     * @param string       $query The query to execute
     * @param DOMNode|null $node  The contextual node
     *
     * @return string The data found
     */
    public function querySingleValue(string $query, DOMNode $node = null): string
    {
        $items = $this->xpath->query($query, $node);

        if ($items->length > 0) {
            return $items->item(0)->nodeValue;
        }

        return '';
    }

    /**
     * Queries for multiple values in the document.
     *
     * @param string       $query The query to execute
     * @param DOMNode|null $node  The contextual node
     *
     * @return string[] The data found
     */
    public function queryMultipleValues(string $query, DOMNode $node = null): array
    {
        $items = $this->xpath->query($query, $node);
        $data  = [];

        if (false === $items) {
            return $data;
        }

        foreach ($items as $item) {
            if (empty($item->nodeValue)) {
                continue;
            }

            $data[] = mb_strtolower($item->nodeValue);
        }

        return $data;
    }

    /**
     * Registers the namespaces of the DOMXPath object. The `$namespaces` argument must be
     * structured as:.
     *
     * ```
     * [
     *   {namespace prefix}: {namespace uri}
     * ]
     * ```
     *
     * @param string[] $namespaces The namespaces to register
     */
    private function registerNamespaces(array $namespaces): void
    {
        foreach ($namespaces as $acronym => $namespace) {
            $this->xpath->registerNamespace($acronym, $namespace);
        }
    }
}
