<?php

namespace DonlSync\Test\Unit\Helper;

use DonlSync\Helper\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperTest extends TestCase
{
    public function testLtrimTakesNoActionWhenPatternDoesNotMatch(): void
    {
        $input = 'foo';

        $this->assertEquals($input, StringHelper::ltrim($input, 'bar'));
    }

    public function testLtrimRemovesPatternMatch(): void
    {
        $this->assertEquals('foo', StringHelper::ltrim('barfoo', 'bar'));
    }

    public function testLtrimOnlyRemovesPatternMatchesAtStart(): void
    {
        $input = 'foobar';

        $this->assertEquals($input, StringHelper::ltrim($input, 'bar'));
    }

    public function testLtrimIsMultiByteAware(): void
    {
        $input = 'X生';

        $this->assertEquals($input, StringHelper::ltrim($input, '生'));
        $this->assertEquals('生', StringHelper::ltrim($input, 'X'));
    }

    public function testDateStringIsProperlyFormatted(): void
    {
        $input    = '20200101';
        $expected = '01/01/2020';

        $this->assertEquals($expected, StringHelper::formatNonDateString($input));
    }

    public function testRepairURLWithWrongSlashes(): void
    {
        $cases = [
            'http:\\example.com'  => 'https://example.com',
            'https:\\example.com' => 'https://example.com',
        ];

        foreach ($cases as $input => $output) {
            $this->assertEquals($output, StringHelper::repairURL($input));
        }
    }

    public function testRepairURLWithWWWAndNoProtocol(): void
    {
        $this->assertEquals(
            'https://www.example.com',
            StringHelper::repairURL('www.example.com')
        );
    }

    public function testRepairURLEncodesSpaces(): void
    {
        $this->assertEquals(
            'https://example.com/foo%20bar/',
            StringHelper::repairURL('https://example.com/foo bar/')
        );
    }

    public function testRepairURLEncodesAmpersands(): void
    {
        $this->assertEquals(
            'https://example.com?foo=bar&baz=foo',
            StringHelper::repairURL('https://example.com?foo=bar&amp;baz=foo')
        );
    }

    public function testRepairURLProtocolCorrections(): void
    {
        $this->assertEquals(
            'https://example.com',
            StringHelper::repairURL('example.com')
        );

        $this->assertEquals(
            'http://example.com',
            StringHelper::repairURL('http://example.com')
        );
    }

    public function testAddFallbackProtocolToURL(): void
    {
        $this->assertEquals(
            'https://waarismijnstemlokaal.nl',
            StringHelper::repairURL('waarismijnstemlokaal.nl')
        );
    }
}
