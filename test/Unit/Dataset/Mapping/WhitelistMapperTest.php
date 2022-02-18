<?php

namespace DonlSync\Test\Unit\Dataset\Mapping;

use DonlSync\Dataset\Mapping\WhitelistMapper;

class WhitelistMapperTest extends AbstractMapperTest
{
    public function setUp(): void
    {
        $this->mapper_class = WhitelistMapper::class;

        parent::setUp();
    }

    public function testValuesInMapAreConsideredBlacklisted(): void
    {
        $mapping = ['foo' => 'bar'];
        $this->mapper->setMap($mapping);

        $this->assertTrue($this->mapper->inWhitelist('bar'));
    }

    public function testValuesNotInMapAreNotBlacklisted(): void
    {
        $mapping = ['foo' => 'bar'];
        $this->mapper->setMap($mapping);

        $this->assertFalse($this->mapper->inWhitelist('foo'));
    }
}
