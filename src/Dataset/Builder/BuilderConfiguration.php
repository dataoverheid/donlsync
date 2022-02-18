<?php

namespace DonlSync\Dataset\Builder;

use DonlSync\Configuration;
use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\MappingLoader;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;

/**
 * Class BuilderConfiguration.
 *
 * Contains the configuration for the BuildRule implementations.
 */
class BuilderConfiguration
{
    /**
     * The mapping implementation for applying default values.
     */
    protected ?DefaultMapper $defaults;

    /**
     * The mapping implementations per field for transforming harvested values.
     *
     * @var ValueMapper[]
     */
    protected array $value_mappers;

    /**
     * The mapping implementations per field for blocking the harvesting of certain metadata values.
     *
     * @var BlacklistMapper[]
     */
    protected array $blacklists;

    /**
     * The mapping implementations per field for only allowing the harvesting of certain metadata
     * values.
     *
     * @var WhitelistMapper[]
     */
    protected array $whitelists;

    /**
     * BuilderConfiguration constructor.
     */
    public function __construct()
    {
        $this->defaults      = null;
        $this->value_mappers = [];
        $this->blacklists    = [];
        $this->whitelists    = [];
    }

    /**
     * Loads all external resources and constructs a BuilderConfiguration instance for a source
     * catalog.
     *
     * @param Configuration $config The catalog config
     * @param Client        $client The HTTP client to use
     * @param MappingLoader $loader The mapping loader to use
     *
     * @throws MappingException       On any error instantiating the mapping objects
     * @throws ConfigurationException On any configuration related error
     *
     * @return BuilderConfiguration The created BuilderConfiguration instance
     */
    public static function loadBuilderConfigurations(Configuration $config, Client $client,
                                                     MappingLoader $loader): BuilderConfiguration
    {
        $mapping_config = $config->get('mappings');
        $builder_config = new BuilderConfiguration();

        $builder_config->setDefaults(
            $loader->loadDefaultMappings($mapping_config['defaults'], $client)
        );
        $builder_config->setValueMappers(
            $loader->loadMappingFromURL(
                $mapping_config['transformations'], ValueMapper::class, $client
            )
        );
        $builder_config->setBlacklists(
            $loader->loadMappingFromURL(
                $mapping_config['blacklists'], BlacklistMapper::class, $client)
        );
        $builder_config->setWhitelists(
            $loader->loadMappingFromURL(
                $mapping_config['whitelists'], WhitelistMapper::class, $client
            )
        );

        return $builder_config;
    }

    /**
     * Getter for the configured defaults.
     *
     * @return DefaultMapper|null The defaults
     */
    public function getDefaults(): ?DefaultMapper
    {
        return $this->defaults;
    }

    /**
     * Getter for the configured value mappers.
     *
     * @return ValueMapper[] The value mappers
     */
    public function getValueMappers(): array
    {
        return $this->value_mappers;
    }

    /**
     * Getter for the configured blacklists.
     *
     * @return BlacklistMapper[] The blacklists
     */
    public function getBlacklists(): array
    {
        return $this->blacklists;
    }

    /**
     * Getter for the configured whitelists.
     *
     * @return WhitelistMapper[] The whitelists
     */
    public function getWhitelists(): array
    {
        return $this->whitelists;
    }

    /**
     * Assigns defaults to use.
     *
     * @param DefaultMapper $defaults The defaults to use
     */
    public function setDefaults(DefaultMapper $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * Assigns value mappers to use.
     *
     * @param ValueMapper[] $value_mappers The value mappers to use
     */
    public function setValueMappers(array $value_mappers): void
    {
        $this->value_mappers = $value_mappers;
    }

    /**
     * Assigns blacklists to use.
     *
     * @param BlacklistMapper[] $blacklists The blacklists to use
     */
    public function setBlacklists(array $blacklists): void
    {
        $this->blacklists = $blacklists;
    }

    /**
     * Assigns whitelists to use.
     *
     * @param WhitelistMapper[] $whitelists The whitelists to use
     */
    public function setWhitelists(array $whitelists): void
    {
        $this->whitelists = $whitelists;
    }
}
