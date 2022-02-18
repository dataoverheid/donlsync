<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATNumber;

/**
 * Class DCATNumberBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATNumber` object.
 *
 * @see \DCAT_AP_DONL\DCATNumber
 */
class DCATNumberBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     *
     * @return DCATNumber|null The created DCATNumber
     */
    public function build(array &$data, array &$notices): ?DCATNumber
    {
        return $this->buildSingleProperty($data, $notices, DCATNumber::class);
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATNumber[] The created DCATNumbers
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_numbers = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_numbers;
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

            $dcat_number = new DCATNumber($data[$this->property][$i]);

            if (!$dcat_number->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_number->getData()
                );

                $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property][$i]);

                continue;
            }

            $dcat_numbers[] = $dcat_number;
        }

        return $this->stripDuplicates($dcat_numbers);
    }
}
