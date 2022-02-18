<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATTemporal;

/**
 * Class DCATTemporalBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATTemporal` object.
 *
 * @see \DCAT_AP_DONL\DCATTemporal
 */
class DCATTemporalBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     *
     * @return DCATTemporal|null The created DCATTemporal
     */
    public function build(array &$data, array &$notices): ?DCATTemporal
    {
        $label = ($this->createLiteralBuildRule('temporal_label'))
            ->build($data, $notices);
        $start = ($this->createDateTimeBuildRule('temporal_start'))
            ->build($data, $notices);
        $end   = ($this->createDateTimeBuildRule('temporal_end'))
            ->build($data, $notices);

        if (!$label && !$start && !$end) {
            return null;
        }

        $dcat_temporal = new DCATTemporal();

        if ($label) {
            $dcat_temporal->setLabel($label);
        }

        if ($start) {
            $dcat_temporal->setStart($start);
        }

        if ($end) {
            $dcat_temporal->setEnd($end);
        }

        if (!$dcat_temporal->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value is not valid, discarding',
                $this->prefix, ucfirst($this->property)
            );

            return null;
        }

        return $dcat_temporal;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATTemporal[] The created DCATTemporals
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // multiple temporal builder not supported (yet?).

        return [];
    }
}
