<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATEntity;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\DonlSyncRuntimeException;

/**
 * Class AccessRightsBuildRule.
 *
 * Calculates the value of the accessRights attribute based on the value of the license attribute.
 */
class AccessRightsBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /** @var string */
    private const ACCESS_RIGHTS_VOCABULARY = 'Overheid:Openbaarheidsniveau';

    /** @var array */
    private $dcat_config;

    /**
     * {@inheritdoc}
     *
     * @param BuilderConfiguration|null $config      The mapping configuration
     * @param array                     $dcat_config The DCAT config to use
     */
    public function __construct(string $property, string $prefix = 'Dataset',
                                BuilderConfiguration $config = null, array $dcat_config = [])
    {
        parent::__construct($property, $prefix);

        if (!$config) {
            return;
        }

        if (empty($dcat_config)) {
            throw new DonlSyncRuntimeException('empty $dcat_config provided');
        }

        $this->dcat_config   = $dcat_config;
        $this->defaults      = $config->getDefaults();
        $this->value_mappers = $config->getValueMappers();
        $this->blacklists    = $config->getBlacklists();
        $this->whitelists    = $config->getWhitelists();
    }

    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        $license       = $data['license'];
        $access_rights = (in_array($license, $this->dcat_config['license']['closed']))
            ? $this->dcat_config['access_rights']['restricted']
            : $this->dcat_config['access_rights']['public'];

        $notices[] = sprintf('%s: %s: mapped to %s based on license',
            $this->prefix, ucfirst($this->property), $access_rights
        );

        return new DCATControlledVocabularyEntry(
            $access_rights, self::ACCESS_RIGHTS_VOCABULARY
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // not supported

        return [];
    }
}
