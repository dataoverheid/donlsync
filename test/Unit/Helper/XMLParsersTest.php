<?php

namespace DonlSync\Test\Unit\Helper;

use DOMNodeList;
use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\XMLParser;
use Exception;
use PHPUnit\Framework\TestCase;

class XMLParsersTest extends TestCase
{
    public function testThrowsExceptionOnMissingConfigurationKeys(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Missing configuration key: namespaces');

        new XMLParser('', []);
    }

    public function testThrowsExceptionOnInvalidXML(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Invalid XML provided');

        new XMLParser('', ['namespaces' => []]);
    }

    public function testPassesThroughConstructorWithValidXML(): void
    {
        try {
            new XMLParser('<xml/>', ['namespaces' => []]);

            $this->assertTrue(true);
        } catch (DonlSyncRuntimeException $e) {
            $this->fail('unexpected DonlSyncRuntimeException thrown');
        }
    }

    public function testQueryReturnsFalseOnInvalidXPathSelector(): void
    {
        $parser = new XMLParser('<xml/>', ['namespaces' => []]);

        $this->assertFalse($parser->query('%!@'));
    }

    public function testQueryReturnsArrayOnValidXPathSelector(): void
    {
        $parser = new XMLParser('<xml/>', ['namespaces' => []]);

        $this->assertInstanceOf(DOMNodeList::class, $parser->query('/foo'));
    }

    public function testSingleValueReturnsEmptyStringWhenNoMatchesAreFound(): void
    {
        $parser = new XMLParser('<xml/>', ['namespaces' => []]);

        $this->assertEmpty($parser->querySingleValue('/xml/foo'));
    }

    public function testSingleValueReturnsTheFirstValueFound(): void
    {
        $parser = new XMLParser('
            <xml>
              <foo>bar</foo>
              <foo>baz</foo>
            </xml>
        ', ['namespaces' => []]);

        $this->assertEquals('bar', $parser->querySingleValue('/xml/foo'));
    }

    public function testRegisteringNamespaces(): void
    {
        try {
            $parser = new XMLParser('
                <xml>
                  <foo>bar</foo>
                  <foo>baz</foo>
                </xml>
            ', ['namespaces' => [
                'foo' => 'bar',
            ]]);

            $this->assertInstanceOf(XMLParser::class, $parser);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testSingleValueReturnsTheFirstValueFoundEvenIfEmpty(): void
    {
        $parser = new XMLParser('
            <xml>
              <foo/>
              <foo>baz</foo>
            </xml>
        ', ['namespaces' => []]);

        $this->assertEquals('', $parser->querySingleValue('/xml/foo'));
    }

    public function testMultipleReturnsEmptyArrayOnInvalidXPathSelector(): void
    {
        $parser = new XMLParser('<xml/>', ['namespaces' => []]);
        $result = $parser->queryMultipleValues('%!@');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testMultipleReturnsEmptyArrayWhenNoResultsAreFound(): void
    {
        $parser = new XMLParser('<xml/>', ['namespaces' => []]);
        $result = $parser->queryMultipleValues('/xml/foo');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testMultipleIgnoresEmptyNodes(): void
    {
        $parser = new XMLParser('
            <xml>
              <foo/>
              <foo>bar</foo>
            </xml>
        ', ['namespaces' => []]);
        $result = $parser->queryMultipleValues('/xml/foo');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testMultipleLowerCasesNodeValues(): void
    {
        $parser = new XMLParser('
            <xml>
              <foo/>
              <foo>bAr!</foo>
            </xml>
        ', ['namespaces' => []]);
        $result = $parser->queryMultipleValues('/xml/foo');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('bar!', $result[0]);
    }
}
