<?php

namespace DonlSync\Catalog\Source\NGR\BuildRule;

use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\AccessRightsBuildRule;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class NGRBuildRuleFactory.
 *
 * Responsible for construction all the custom BuildRules in use by the NGR SourceCatalog
 * implementation.
 */
class NGRBuildRuleFactory
{
    /**
     * Getter for all the defined BuildRules for datasets.
     *
     * @param BuilderConfiguration $configuration The configuration to use for the BuildRules
     * @param Configuration        $dcat_config   The DCAT config used by some custom rules
     *
     * @return IDCATEntityBuildRule[] The BuildRules for datasets
     */
    public static function getAllDatasetBuildRules(BuilderConfiguration $configuration,
                                                   Configuration $dcat_config): array
    {
        return [
            'accessRights' => new AccessRightsBuildRule(
                'accessRights', 'Dataset', $configuration, $dcat_config->all()
            ),
            'spatial'      => new NGRSpatialBuildRule('spatial', 'Dataset'),
        ];
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
