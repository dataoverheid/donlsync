<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATBoolean;
use DCAT_AP_DONL\DCATEntity;

/**
 * Class DCATBooleanBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATBoolean` object.
 *
 * @see \DCAT_AP_DONL\DCATBoolean
 */
class DCATBooleanBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
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

        $dcat_boolean = new DCATBoolean($data[$this->property]);

        if (!$dcat_boolean->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                $this->prefix, ucfirst($this->property), $dcat_boolean->getData()
            );

            return null;
        }

        return $dcat_boolean;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_booleans = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_booleans;
        }

        for ($i = 0; $i < count($data[$this->property]); ++$i) {
            if ($this->multiValuedValueIsBlacklisted($this->property, $data, $notices, $i)) {
                continue;
            }

            if (!$this->multiValuedValueIsWhitelisted($this->property, $data, $notices, $i)) {
                continue;
            }

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_boolean = new DCATBoolean($data[$this->property][$i]);

            if (!$dcat_boolean->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_boolean->getData()
                );

                continue;
            }

            $dcat_booleans[] = $dcat_boolean;
        }

        return $this->stripDuplicates($dcat_booleans);
    }
}
