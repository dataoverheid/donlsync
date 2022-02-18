<?php

namespace DonlSync\Test\Unit\Dataset\Builder;

use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\MappingLoader;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class BuilderConfigurationTest extends TestCase
{
    public function testDefaultsToEmptyMappingLists(): void
    {
        $config = new BuilderConfiguration();

        $this->assertNull($config->getDefaults());
        $this->assertEmpty($config->getValueMappers());
        $this->assertEmpty($config->getBlacklists());
        $this->assertEmpty($config->getWhitelists());
    }

    public function testDefaultMapperIsRetrievable(): void
    {
        $mapper = new DefaultMapper();
        $config = new BuilderConfiguration();

        $config->setDefaults($mapper);

        $this->assertEquals($mapper, $config->getDefaults());
    }

    public function testAssignedValueMappersAreRetrievable(): void
    {
        $mappers = [
            'foo' => new ValueMapper(),
            'bar' => new ValueMapper(),
        ];
        $config  = new BuilderConfiguration();

        $config->setValueMappers($mappers);

        $this->assertEqualsCanonicalizing($mappers, $config->getValueMappers());
    }

    public function testAssignedBlacklistMappersAreRetrievable(): void
    {
        $mappers = [
            'foo' => new BlacklistMapper(),
            'bar' => new BlacklistMapper(),
        ];
        $config  = new BuilderConfiguration();

        $config->setBlacklists($mappers);

        $this->assertEqualsCanonicalizing($mappers, $config->getBlacklists());
    }

    public function testAssignedWhitelistMappersAreRetrievable(): void
    {
        $mappers = [
            'foo' => new WhitelistMapper(),
            'bar' => new WhitelistMapper(),
        ];
        $config  = new BuilderConfiguration();

        $config->setWhitelists($mappers);

        $this->assertEqualsCanonicalizing($mappers, $config->getWhitelists());
    }

    public function testConfigurationLoaderThrowsExceptionOnMissingConfigurationKeys(): void
    {
        try {
            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage('No configuration present with key mappings');

            BuilderConfiguration::loadBuilderConfigurations(
                new Configuration([]),
                new Client(),
                new MappingLoader('')
            );
        } catch (MappingException $e) {
            $this->fail('Unexpected MappingException thrown');
        }
    }
}
