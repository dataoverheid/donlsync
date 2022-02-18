<?php

namespace DonlSync\Dataset\Builder\BuildRule;

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
     *
     * @return DCATURI|null The created DCATURI
     */
    public function build(array &$data, array &$notices): ?DCATURI
    {
        return $this->buildSingleProperty($data, $notices, DCATURI::class);
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATURI[] The created DCATURI's
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

            $original_value = $data[$this->property][$i];

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_uri = new DCATURI($data[$this->property][$i]);

            if (!$dcat_uri->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property), $dcat_uri->getData()
                );

                $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property][$i]);

                continue;
            }

            $dcat_uris[] = $dcat_uri;
        }

        return $this->stripDuplicates($dcat_uris);
    }
}
