<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATDateTime;

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
     *
     * @return DCATDateTime|null The created DCATDateTime
     */
    public function build(array &$data, array &$notices): ?DCATDateTime
    {
        return $this->buildSingleProperty($data, $notices, DCATDateTime::class);
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATDateTime[] The created DCATDateTimes
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

            $original_value = $data[$this->property][$i];

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_datetime = new DCATDateTime($data[$this->property][$i]);

            if (!$dcat_datetime->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_datetime->getData()
                );

                $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property][$i]);

                continue;
            }

            $dcat_datetimes[] = $dcat_datetime;
        }

        return $this->stripDuplicates($dcat_datetimes);
    }
}
