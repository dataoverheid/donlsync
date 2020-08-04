<?php

namespace DonlSync\Helper;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Class XMLParser.
 *
 * Enables searching through XML contents for specific data based on named field and/or specific
 * XPath queries.
 */
class XMLParser
{
    /** @var DOMXPath */
    protected $xpath;

    /** @var array */
    protected $xpath_selectors;

    /**
     * XMLParser constructor.
     *
     * @param string $xml             The XML to harvest data from
     * @param array  $xpath_selectors The XPath selectors to query for named fields
     */
    public function __construct(string $xml, array $xpath_selectors)
    {
        $xml_document = new DOMDocument();
        $xml_document->loadXML($xml);

        $this->xpath           = new DOMXPath($xml_document);
        $this->xpath_selectors = $xpath_selectors;

        $this->registerNamespaces($xpath_selectors['namespaces']);
    }

    /**
     * Wrapper for `DOMXPath::query()`.
     *
     * @param string $query The query to execute
     *
     * @return DOMNodeList|bool The search results
     */
    public function query(string $query)
    {
        return $this->xpath->query($query);
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
