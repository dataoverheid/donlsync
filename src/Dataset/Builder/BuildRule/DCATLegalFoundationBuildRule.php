<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATEntity;
use DCAT_AP_DONL\DCATLegalFoundation;
use DCAT_AP_DONL\DCATLiteral;
use DCAT_AP_DONL\DCATURI;

/**
 * Class DCATLegalFoundationBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATLegalFoundation` object.
 *
 * @see \DCAT_AP_DONL\DCATLegalFoundation
 */
class DCATLegalFoundationBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        $label = ($this->createLiteralBuildRule('legal_foundation_label'))
            ->build($data, $notices);
        $uri   = ($this->createURIBuildRule('legal_foundation_uri'))
            ->build($data, $notices);
        $ref   = ($this->createLiteralBuildRule('legal_foundation_ref'))
            ->build($data, $notices);

        if (!$label || !$uri || !$ref) {
            return null;
        }

        $dcat_legal_foundation = new DCATLegalFoundation();

        if ($label) {
            /* @var DCATLiteral $label */
            $dcat_legal_foundation->setLabel($label);
        }

        if ($uri) {
            /* @var DCATURI $uri */
            $dcat_legal_foundation->setUri($uri);
        }

        if ($ref) {
            /* @var DCATLiteral $ref */
            $dcat_legal_foundation->setReference($ref);
        }

        if (!$dcat_legal_foundation->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value is not valid, discarding',
                $this->prefix, ucfirst($this->property)
            );

            return null;
        }

        return $dcat_legal_foundation;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // multiple legalFoundation builder not supported (yet?).

        return [];
    }
}
