<?php

namespace DonlSync\Test\Unit\Dataset\Mapping;

use DonlSync\Dataset\Mapping\DefaultMapper;

class DefaultMapperTest extends AbstractMapperTest
{
    public function setUp(): void
    {
        $this->mapper_class = DefaultMapper::class;

        parent::setUp();
    }

    public function testMapperIndicatesItHasDefaultValueForKeyInMapping(): void
    {
        $mapping = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertTrue($this->mapper->has('foo'));
    }

    public function testMapperIndicatesItHasNoDefaultValueForKeyNotInMapping(): void
    {
        $mapping = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertFalse($this->mapper->has('bar'));
    }

    public function testDefaultIsNullWhenNoDefaultIsSetForGivenKey(): void
    {
        $mapping = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertNull($this->mapper->getDefault('bar'));
    }

    public function testDefaultIsReturnedWhenPresentInMap(): void
    {
        $mapping = ['foo' => 'bar'];

        $this->mapper->setMap($mapping);

        $this->assertNotNull($this->mapper->getDefault('foo'));
        $this->assertEquals('bar', $this->mapper->getDefault('foo'));
    }
}
