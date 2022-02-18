<?php

namespace DonlSync\Test\Unit\Dataset;

use DonlSync\Dataset\DONLDistribution;
use PHPUnit\Framework\TestCase;

class DONLDistributionTest extends TestCase
{
    public function testIdIsNullWhenNotSet(): void
    {
        $distribution = new DONLDistribution();

        $this->assertNull($distribution->getId());
        $this->assertArrayNotHasKey('id', $distribution->getData());
    }

    public function testWhenIdIsSetItIsAvailable(): void
    {
        $test_value   = 'foo';
        $distribution = new DONLDistribution();
        $distribution->setId($test_value);

        $this->assertNotNull($distribution->getId());
        $this->assertEquals($test_value, $distribution->getId());
        $this->assertArrayHasKey('id', $distribution->getData());
        $this->assertEquals($test_value, $distribution->getData()['id']);
    }
}
