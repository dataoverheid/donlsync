<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATLiteral;

/**
 * Class DCATLiteralBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATLiteral` object.
 *
 * @see \DCAT_AP_DONL\DCATLiteral
 */
class DCATLiteralBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     *
     * @return DCATLiteral|null The created DCATLiteral
     */
    public function build(array &$data, array &$notices): ?DCATLiteral
    {
        return $this->buildSingleProperty($data, $notices, DCATLiteral::class);
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATLiteral[] The created DCATLiterals
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_literals = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_literals;
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

            $dcat_literal = new DCATLiteral($data[$this->property][$i]);

            if (!$dcat_literal->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_literal->getData()
                );

                $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property][$i]);

                continue;
            }

            $dcat_literals[] = $dcat_literal;
        }

        return $this->stripDuplicates($dcat_literals);
    }
}
