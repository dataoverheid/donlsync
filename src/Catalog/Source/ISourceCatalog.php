<?php

namespace DonlSync\Catalog\Source;

use DonlSync\Catalog\ICatalog;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Interface ISourceCatalog.
 *
 * Represents a source catalog from which datasets are harvested and sent to a target catalog.
 */
interface ISourceCatalog extends ICatalog
{
    /**
     * Retrieve the credentials used for this source catalog when communicating with the target
     * catalog.
     *
     * Expected keys to return:
     *
     * - `owner_org`
     * - `user_id`
     * - `api_key`
     *
     * @return string[] A {string} => {string} array of credentials
     */
    public function getCredentials(): array;

    /**
     * Getter for the custom dataset BuildRules defined for this ISourceCatalog implementation.
     *
     * @return IDCATEntityBuildRule[] The custom BuildRules for datasets
     */
    public function getDatasetBuildRules(): array;

    /**
     * Getter for the custom distribution BuildRules defined for this ISourceCatalog implementation.
     *
     * @return IDCATEntityBuildRule[] The custom BuildRules for distributions
     */
    public function getDistributionBuildRules(): array;

    /**
     * Getter for the BuilderConfiguration to use for the BuildRules during the dataset creation
     * process.
     *
     * @return BuilderConfiguration The configuration for the BuildRules
     */
    public function getBuilderConfig(): BuilderConfiguration;
}
