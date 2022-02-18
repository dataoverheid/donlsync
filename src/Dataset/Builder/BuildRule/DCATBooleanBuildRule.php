<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATBoolean;

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
     *
     * @return DCATBoolean|null The created DCATBoolean
     */
    public function build(array &$data, array &$notices): ?DCATBoolean
    {
        return $this->buildSingleProperty($data, $notices, DCATBoolean::class);
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATBoolean[] The created DCATBooleans
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

            $original_value = $data[$this->property][$i];

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_boolean = new DCATBoolean($data[$this->property][$i]);

            if (!$dcat_boolean->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_boolean->getData()
                );

                $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property][$i]);

                continue;
            }

            $dcat_booleans[] = $dcat_boolean;
        }

        return $this->stripDuplicates($dcat_booleans);
    }
}
