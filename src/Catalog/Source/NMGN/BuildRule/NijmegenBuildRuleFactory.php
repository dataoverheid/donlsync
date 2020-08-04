<?php

namespace DonlSync\Catalog\Source\NMGN\BuildRule;

use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class NijmegenBuildRuleFactory.
 *
 * Responsible for construction all the custom BuildRules in use by the Nijmegen SourceCatalog
 * implementation.
 */
class NijmegenBuildRuleFactory
{
    /**
     * Getter for all the defined BuildRules for datasets.
     *
     * @param BuilderConfiguration $configuration The configuration to use for the BuildRules
     *
     * @return IDCATEntityBuildRule[] The BuildRules for datasets
     */
    public static function getAllDatasetBuildRules(BuilderConfiguration $configuration): array
    {
        return [];
    }

    /**
     * Getter for all the defined BuildRules for distributions.
     *
     * @param BuilderConfiguration $configuration The configuration to use for the BuildRules
     *
     * @return IDCATEntityBuildRule[] The BuildRules for distributions
     */
    public static function getAllDistributionBuildRules(BuilderConfiguration $configuration): array
    {
        return [];
    }
}
