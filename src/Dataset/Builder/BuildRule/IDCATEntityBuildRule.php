<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATEntity;
use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;

/**
 * Interface IDCATEntityBuildRule.
 *
 * Defines the contract that all BuildRules must follow in order for the DatasetBuilder to be able
 * to interact with it.
 *
 * @see \DonlSync\Dataset\Builder\DatasetBuilder
 */
interface IDCATEntityBuildRule
{
    /**
     * IDCATEntityBuildRule constructor.
     *
     * @param string $property The property this BuildRule is responsible for
     * @param string $prefix   The prefix used for generating notices
     */
    public function __construct(string $property, string $prefix = 'Dataset');

    /**
     * Replaces the prefix with a new value.
     *
     * @param string $prefix The new prefix to use
     */
    public function setPrefix(string $prefix): void;

    /**
     * Attempts to create a single DCATEntity based on the given data and the configured Mapping
     * implementations. May return `null` if no DCATEntity can be constructed.
     *
     * @param array    $data    The data harvested from the catalog
     * @param string[] $notices The notices generated during the dataset building process
     *
     * @return DCATEntity|null The created DCATEntity or null if no valid DCATEntity could be
     *                         created
     */
    public function build(array &$data, array &$notices): ?DCATEntity;

    /**
     * Attempts to create more than one DCATEntity for a given property based on the given data and
     * the configured Mapping implementations. May return an empty array if no DCATEntity's can be
     * constructed.
     *
     * @param array    $data    The data harvested from the catalog
     * @param string[] $notices The notices generated during the dataset building process
     *
     * @return DCATEntity[] The created DCATEntities
     *
     * @see \DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule::build()
     */
    public function buildMultiple(array &$data, array &$notices): array;

    /**
     * Getter for the DCAT attribute for which this BuildRule is implemented.
     *
     * @return string The property set
     */
    public function getProperty(): string;

    /**
     * The configured defaults for the ISourceCatalog.
     *
     * @return DefaultMapper The defaults for this BuildRule
     */
    public function getDefaults(): DefaultMapper;

    /**
     * The configured ValueMappers for the ISourceCatalog.
     *
     * @return ValueMapper[] The value mappers for this BuildRule
     */
    public function getValueMappers(): array;

    /**
     * The configured BlacklistMapper for the ISourceCatalog.
     *
     * @return BlacklistMapper[] The blacklists for this BuildRule
     */
    public function getBlacklists(): array;

    /**
     * The configured WhitelistMapper for the ISourceCatalog.
     *
     * @return WhitelistMapper[] The whitelists for this BuildRule
     */
    public function getWhitelists(): array;

    /**
     * Setter for the defaults of this BuildRule.
     *
     * @param DefaultMapper $defaults The defaults to set
     */
    public function setDefaults(DefaultMapper $defaults): void;

    /**
     * Setter for the value mappers of this BuildRule.
     *
     * @param ValueMapper[] $mappers The ValueMappers to set
     */
    public function setValueMappers(array $mappers): void;

    /**
     * Setter for the blacklists of this BuildRule.
     *
     * @param BlacklistMapper[] $mappers The blacklists to set
     */
    public function setBlacklists(array $mappers): void;

    /**
     * Setter for the whitelists of this BuildRule.
     *
     * @param WhitelistMapper[] $mappers The whitelists to set
     */
    public function setWhitelists(array $mappers): void;
}
