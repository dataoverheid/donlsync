<?php

namespace DonlSync\Catalog\Source\ODataCatalog\BuildRule;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATException;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Dataset\Builder\BuildRule\AbstractDCATEntityBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATControlledVocabularyEntryBuildRule;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;

/**
 * Class ODataCatalogThemeBuildRule.
 *
 * Executes the standard procedure to construct the themes of a dataset, but will fallback to a
 * pre-configured default should no valid themes be constructed.
 */
class ODataCatalogThemeBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * The vocabulary to use for the theme property.
     *
     * @var string
     */
    private const VOCABULARY = 'Overheid:Taxonomiebeleidsagenda';

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
     * @return DCATControlledVocabularyEntry|null The created DCATControlledVocabularyEntry
     */
    public function build(array &$data, array &$notices): ?DCATControlledVocabularyEntry
    {
        // not supported

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATControlledVocabularyEntry[] The created DCATControlledVocabularyEntries
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $themes_builder = new DCATControlledVocabularyEntryBuildRule(
            $this->property, 'Dataset', self::VOCABULARY
        );
        $themes_builder->setDefaults($this->defaults);
        $themes_builder->setValueMappers($this->value_mappers);
        $themes_builder->setBlacklists($this->blacklists);
        $themes_builder->setWhitelists($this->whitelists);

        $themes = $themes_builder->buildMultiple($data, $notices);

        if (0 == count($themes)) {
            if (!$this->defaults->has($this->property)) {
                return [];
            }

            $default   = $this->defaults->getDefault($this->property);
            $notices[] = sprintf(
                '%s: %s: no valid themes constructed, attempting to fallback to default',
                $this->prefix, ucfirst($this->property)
            );

            try {
                $theme      = new DCATControlledVocabularyEntry(
                    $default, self::VOCABULARY
                );
                $validation = $theme->validate();

                if (!$validation->validated()) {
                    $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                        $this->prefix, ucfirst($this->property), $default
                    );

                    return [];
                }

                $notices[] = sprintf('%s: %s: using default value %s',
                    $this->prefix, ucfirst($this->property), $default
                );

                return [$theme];
            } catch (DCATException $e) {
                $notices[] = sprintf('%s: %s: invalid vocabulary %s defined for %s',
                    $this->prefix, ucfirst($this->property), self::VOCABULARY, $default
                );

                return [];
            }
        }

        return $themes;
    }
}
