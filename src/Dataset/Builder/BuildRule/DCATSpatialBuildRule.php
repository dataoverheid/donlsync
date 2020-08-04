<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATEntity;

/**
 * Class DCATSpatialBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATSpatial` object.
 *
 * @see \DCAT_AP_DONL\DCATSpatial
 */
class DCATSpatialBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        // single spatial builder not supported (yet?).

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // multiple spatial builder not supported (yet?).

        return [];
    }
}
