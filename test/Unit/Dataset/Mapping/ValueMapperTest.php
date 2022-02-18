<?php

namespace DonlSync\Test\Unit\Dataset\Mapping;

use DonlSync\Dataset\Mapping\ValueMapper;

class ValueMapperTest extends AbstractMapperTest
{
    public function setUp(): void
    {
        $this->mapper_class = ValueMapper::class;

        parent::setUp();
    }

    public function testOriginalValueIsReturnedWhenNoMappingIsPresent(): void
    {
        $test_value = 'bar';
        $mapping    = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertEquals($test_value, $this->mapper->map($test_value));
    }

    public function testMappedValueIsReturnedWhenMappingIsPresent(): void
    {
        $mapping = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertEquals('bar', $this->mapper->map('foo'));
    }

    public function testMappingsAreNotCaseSensitive(): void
    {
        $mapping = ['fOo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertEquals('bar', $this->mapper->map('foO'));
    }

    public function testMappingsAreMultiByteSafe(): void
    {
        $mapping = [
            'X生'  => 'bar',
            'X'   => 'foo',
        ];

        $this->mapper->setMap($mapping);

        $this->assertEquals('bar', $this->mapper->map('X生'));
        $this->assertEquals('foo', $this->mapper->map('X'));
    }
}
