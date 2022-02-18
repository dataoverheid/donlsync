<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATSpatial;

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
     *
     * @return DCATSpatial|null The created DCATSpatial
     */
    public function build(array &$data, array &$notices): ?DCATSpatial
    {
        // single spatial builder not supported (yet?).

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATSpatial[] The created DCATSpatials
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // multiple spatial builder not supported (yet?).

        return [];
    }
}
