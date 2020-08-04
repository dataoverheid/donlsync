<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATEntity;
use DCAT_AP_DONL\DCATURI;

/**
 * Class DCATURIBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATURI` object.
 *
 * @see \DCAT_AP_DONL\DCATURI
 */
class DCATURIBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
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

        $dcat_uri = new DCATURI($data[$this->property]);

        if (!$dcat_uri->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                $this->prefix, ucfirst($this->property), $dcat_uri->getData()
            );

            return null;
        }

        return $dcat_uri;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_uris = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_uris;
        }

        for ($i = 0; $i < count($data[$this->property]); ++$i) {
            if ($this->multiValuedValueIsBlacklisted($this->property, $data, $notices, $i)) {
                continue;
            }

            if (!$this->multiValuedValueIsWhitelisted($this->property, $data, $notices, $i)) {
                continue;
            }

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_uri = new DCATURI($data[$this->property][$i]);

            if (!$dcat_uri->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_uri->getData()
                );

                continue;
            }

            $dcat_uris[] = $dcat_uri;
        }

        return $this->stripDuplicates($dcat_uris);
    }
}
