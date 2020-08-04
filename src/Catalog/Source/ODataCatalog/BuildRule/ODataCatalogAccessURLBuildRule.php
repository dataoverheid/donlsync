<?php

namespace DonlSync\Catalog\Source\ODataCatalog\BuildRule;

use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class ODataCatalogAccessURLBuildRule.
 *
 * Calculates the value of a resource's AccessURL based on the harvested catalog, the id of the
 * dataset and a mapping list.
 */
class ODataCatalogAccessURLBuildRule extends ODataCatalogURLBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $property, string $prefix = 'Dataset',
                                BuilderConfiguration $config = null)
    {
        parent::__construct($property, $prefix, $config);

        $this->required_fields = ['accessURL', 'cbs_id', 'cbs_url_lang'];
        $this->field           = 'accessURL';
    }
}
