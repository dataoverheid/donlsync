<?php

namespace DonlSync\Dataset\Builder;

use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class DatasetBuilderBuilder.
 *
 * Responsible for preparing and creating the DatasetBuilder objects used during the synchronization
 * process.
 */
class DatasetBuilderBuilder
{
    /**
     * The DatasetBuilder instance being built.
     */
    protected DatasetBuilder $build;

    /**
     * DatasetBuilderBuilder constructor.
     */
    public function __construct()
    {
        $this->build = new DatasetBuilder();
    }

    public static function buildFromSourceCatalog(ISourceCatalog $catalog): DatasetBuilder
    {
        return (new self())
            ->withBuilderConfiguration($catalog->getBuilderConfig())
            ->withCustomDatasetBuildRules($catalog->getDatasetBuildRules())
            ->withCustomDistributionBuildRules($catalog->getDistributionBuildRules())
            ->build();
    }

    /**
     * Returns the build DatasetBuilder.
     *
     * @return DatasetBuilder The build object
     */
    public function build(): DatasetBuilder
    {
        return $this->build;
    }

    /**
     * Adds a BuilderConfiguration to the built which contains the mapping data for the builder.
     *
     * @param BuilderConfiguration $configuration The object containing the mapping data
     *
     * @return DatasetBuilderBuilder This, for method chaining
     */
    public function withBuilderConfiguration(BuilderConfiguration $configuration): DatasetBuilderBuilder
    {
        $this->build->setDefaultValues($configuration->getDefaults());
        $this->build->setValueMappings($configuration->getValueMappers());
        $this->build->setBlacklistMappings($configuration->getBlacklists());
        $this->build->setWhitelistMappings($configuration->getWhitelists());

        return $this;
    }

    /**
     * Adds custom Dataset build rules to the builder.
     *
     * @param IDCATEntityBuildRule[] $rules The custom build rules
     *
     * @return DatasetBuilderBuilder This, for method chaining
     */
    public function withCustomDatasetBuildRules(array $rules): DatasetBuilderBuilder
    {
        $this->build->setCustomDatasetBuildRules($rules);

        return $this;
    }

    /**
     * Adds custom Distribution build rules to the builder.
     *
     * @param IDCATEntityBuildRule[] $rules The custom build rules
     *
     * @return DatasetBuilderBuilder This, for method chaining
     */
    public function withCustomDistributionBuildRules(array $rules): DatasetBuilderBuilder
    {
        $this->build->setCustomDistributionBuildRules($rules);

        return $this;
    }
}
