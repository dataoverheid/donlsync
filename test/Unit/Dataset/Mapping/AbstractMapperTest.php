<?php

namespace DonlSync\Test\Unit\Dataset\Mapping;

use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\LicenseValueMapper;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;
use PHPUnit\Framework\TestCase;

abstract class AbstractMapperTest extends TestCase
{
    /** @var BlacklistMapper|DefaultMapper|LicenseValueMapper|ValueMapper|WhitelistMapper */
    protected $mapper;

    /** @var string */
    protected $mapper_class;

    /** @var array */
    protected $initial_map;

    public function setUp(): void
    {
        parent::setUp();

        $this->initial_map = [];
        $this->mapper      = new $this->mapper_class($this->initial_map);
    }

    public function testInitialMapIsRetrievable(): void
    {
        $this->assertEquals($this->initial_map, $this->mapper->getFullMap());
    }

    public function testAssignedMapIsOverridable(): void
    {
        $new_map = ['foo' => 'bar'];

        $this->mapper->setMap($new_map);

        $this->assertEquals($new_map, $this->mapper->getFullMap());
    }
}
