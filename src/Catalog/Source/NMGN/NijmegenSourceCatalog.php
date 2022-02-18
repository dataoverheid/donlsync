<?php

namespace DonlSync\Catalog\Source\NMGN;

use DonlSync\Catalog\Source\CKAN\CKANCatalog;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NMGN\BuildRule\NijmegenBuildRuleFactory;
use DonlSync\Helper\StringHelper;

/**
 * Class NijmegenSourceCatalog.
 *
 * Represents the Nijmegen open data catalog.
 */
class NijmegenSourceCatalog extends CKANCatalog implements ISourceCatalog
{
    /**
     * {@inheritdoc}
     */
    public function getDatasetBuildRules(): array
    {
        return NijmegenBuildRuleFactory::getAllDatasetBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return NijmegenBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return array_map(function (array $dataset) {
            if (!empty($dataset['resources'])) {
                $dataset['resources'] = array_map(function (array $resource) {
                    if (!empty($resource['accessURL'])) {
                        $resource['accessURL'] = StringHelper::repairURL($resource['accessURL']);
                    }

                    return $resource;
                }, $dataset['resources']);
            }

            return $dataset;
        }, parent::getData());
    }
}
