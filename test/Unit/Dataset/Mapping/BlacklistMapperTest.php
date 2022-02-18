<?php

namespace DonlSync\Test\Unit\Dataset\Mapping;

use DonlSync\Dataset\Mapping\BlacklistMapper;

class BlacklistMapperTest extends AbstractMapperTest
{
    public function setUp(): void
    {
        $this->mapper_class = BlacklistMapper::class;

        parent::setUp();
    }

    public function testValuesInMapAreConsideredBlacklisted(): void
    {
        $mapping = ['foo' => 'bar'];
        $this->mapper->setMap($mapping);

        $this->assertTrue($this->mapper->isBlacklisted('bar'));
    }

    public function testValuesNotInMapAreNotBlacklisted(): void
    {
        $mapping = ['foo' => 'bar'];
        $this->mapper->setMap($mapping);

        $this->assertFalse($this->mapper->isBlacklisted('foo'));
    }
}
