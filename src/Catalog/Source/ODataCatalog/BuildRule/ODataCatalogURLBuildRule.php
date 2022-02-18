<?php

namespace DonlSync\Catalog\Source\ODataCatalog\BuildRule;

use DCAT_AP_DONL\DCATURI;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\AbstractDCATEntityBuildRule;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class ODataCatalogURLBuildRule.
 *
 * Calculates the value of a ODataCatalog URL field based on various inputs.
 */
abstract class ODataCatalogURLBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * The fields required for constructing the URL property.
     *
     * @var string[]
     */
    protected array $required_fields;

    /**
     * The field containing the metadata from which to construct the property.
     */
    protected string $field;

    /**
     * {@inheritdoc}
     *
     * @param BuilderConfiguration|null $config The mapping configuration
     */
    public function __construct(string $property, string $prefix = 'Dataset',
                                BuilderConfiguration $config = null)
    {
        parent::__construct($property, $prefix);

        if (!$config) {
            return;
        }

        $this->defaults      = $config->getDefaults();
        $this->value_mappers = $config->getValueMappers();
        $this->blacklists    = $config->getBlacklists();
        $this->whitelists    = $config->getWhitelists();
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATURI|null The created DCATURI
     */
    public function build(array &$data, array &$notices): ?DCATURI
    {
        foreach ($this->required_fields as $key) {
            if (!array_key_exists($key, $data)) {
                return null;
            }
        }

        if (!array_key_exists($this->field, $this->value_mappers)) {
            return null;
        }

        $url = $this->value_mappers[$this->field]->map($data[$this->field]);
        $url = sprintf($url, $data['cbs_url_lang'], $data['cbs_id']);

        $notices[] = sprintf('%s: %s: mapped to %s based on CBS ID %s',
            $this->prefix, ucfirst($this->property), $url, $data['cbs_id']
        );

        $dcat_uri = new DCATURI($url);

        if (!$dcat_uri->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value %s is invalid, discarding',
                $this->prefix, ucfirst($this->property), $dcat_uri->getData()
            );

            return null;
        }

        return $dcat_uri;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATURI[] The created DCATURI's
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // not supported

        return [];
    }
}
