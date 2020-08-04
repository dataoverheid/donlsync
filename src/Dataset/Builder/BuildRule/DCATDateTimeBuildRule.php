<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATDateTime;
use DCAT_AP_DONL\DCATEntity;

/**
 * Class DCATDateTimeBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATDateTime` object.
 *
 * @see \DCAT_AP_DONL\DCATDateTime
 */
class DCATDateTimeBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        if (!$this->valueIsPresent($this->property, $data, $notices)) {
            return null;
        }

        if ($this->valueIsBlacklisted($this->property, $data, $notices)) {
            return null;
        }

        if (!$this->valueIsWhitelisted($this->property, $data, $notices)) {
            return null;
        }

        $this->applyValueMapping($this->property, $data, $notices);

        $dcat_datetime = new DCATDateTime($data[$this->property]);

        if (!$dcat_datetime->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                $this->prefix, ucfirst($this->property), $dcat_datetime->getData()
            );

            return null;
        }

        return $dcat_datetime;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_datetimes = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_datetimes;
        }

        for ($i = 0; $i < count($data[$this->property]); ++$i) {
            if ($this->multiValuedValueIsBlacklisted($this->property, $data, $notices, $i)) {
                continue;
            }

            if (!$this->multiValuedValueIsWhitelisted($this->property, $data, $notices, $i)) {
                continue;
            }

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_datetime = new DCATDateTime($data[$this->property][$i]);

            if (!$dcat_datetime->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_datetime->getData()
                );

                continue;
            }

            $dcat_datetimes[] = $dcat_datetime;
        }

        return $this->stripDuplicates($dcat_datetimes);
    }
}
