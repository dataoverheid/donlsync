<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\DonlSyncRuntimeException;

/**
 * Class AccessRightsBuildRule.
 *
 * Calculates the value of the accessRights attribute based on the value of the license attribute.
 */
class AccessRightsBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * The vocabulary to use for access rights properties.
     *
     * @var string
     */
    private const ACCESS_RIGHTS_VOCABULARY = 'Overheid:Openbaarheidsniveau';

    /**
     * The DCAT configuration data.
     *
     * @var array<string, mixed>
     */
    private array $dcat_config;

    /**
     * {@inheritdoc}
     *
     * @param BuilderConfiguration|null $config      The mapping configuration
     * @param array<string, mixed>      $dcat_config The DCAT config to use
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
     *
     * @return DCATControlledVocabularyEntry|null The created DCATControlledVocabularyEntry
     */
    public function build(array &$data, array &$notices): ?DCATControlledVocabularyEntry
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
     *
     * @return DCATControlledVocabularyEntry[] The created DCATControlledVocabularyEntries
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // not supported

        return [];
    }
}
