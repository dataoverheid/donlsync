<?php

namespace DonlSync\Catalog\Source\ODataCatalog\BuildRule;

use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class ODataCatalogLandingPageBuildRule.
 *
 * Calculates the value of the landingPage based on the harvested catalog and the id of the dataset.
 */
class ODataCatalogLandingPageBuildRule extends ODataCatalogURLBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $property, string $prefix = 'Dataset',
                                BuilderConfiguration $config = null)
    {
        parent::__construct($property, $prefix, $config);

        $this->required_fields = ['landingPage', 'cbs_id', 'cbs_url_lang'];
        $this->field           = 'landingPage';
    }
}
