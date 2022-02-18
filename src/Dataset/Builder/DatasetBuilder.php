<?php

namespace DonlSync\Dataset\Builder;

use DCAT_AP_DONL\DCATDataset;
use DonlSync\Dataset\Builder\BuildRule\DCATBooleanBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATContactPointBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATControlledVocabularyEntryBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATDateTimeBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATLegalFoundationBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATLiteralBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATSpatialBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATTemporalBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DCATURIBuildRule;
use DonlSync\Dataset\Builder\BuildRule\DONLDistributionBuildRule;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;

/**
 * Class DatasetBuilder.
 *
 * Responsible for creating datasets according to the DCAT-AP-DONL 1.1 metadata standard. The
 * created datasets may not be valid. The builder works with the data its given.
 */
class DatasetBuilder
{
    /**
     * The mapping implementation for applying default values.
     */
    protected ?DefaultMapper $default_values;

    /**
     * The mapping implementations per field for transforming harvested values.
     *
     * @var ValueMapper[]
     */
    protected array $value_mappings;

    /**
     * The mapping implementations per field for blocking the harvesting of certain metadata values.
     *
     * @var BlacklistMapper[]
     */
    protected array $blacklist_mappings;

    /**
     * The mapping implementations per field for only allowing the harvesting of certain metadata
     * values.
     *
     * @var WhitelistMapper[]
     */
    protected array $whitelist_mappings;

    /**
     * The custom build rules per field to use for said field for datasets.
     *
     * @var IDCATEntityBuildRule[]
     */
    protected array $custom_dataset_build_rules;

    /**
     * The custom build rules per field to use for said field for distributions.
     *
     * @var IDCATEntityBuildRule[]
     */
    protected array $custom_distribution_build_rules;

    /**
     * DatasetBuilder constructor.
     */
    public function __construct()
    {
        $this->default_values                  = null;
        $this->value_mappings                  = [];
        $this->blacklist_mappings              = [];
        $this->whitelist_mappings              = [];
        $this->custom_dataset_build_rules      = [];
        $this->custom_distribution_build_rules = [];
    }

    /**
     * Constructs a dataset according to the DCAT-AP-DONL 1.1 metadata standard.
     *
     * @param string               $catalog_name The name of the catalog from which the dataset is
     *                                           harvested
     * @param array<string, mixed> $source_data  The data from which to create a dataset
     *
     * @return DatasetContainer The container holding the created dataset
     */
    public function buildDataset(string $catalog_name, array $source_data): DatasetContainer
    {
        $notices = [];
        $dataset = new DCATDataset();

        $this->buildURI('identifier', $source_data, $dataset, $notices);
        $this->buildURI('alternateIdentifier', $source_data, $dataset, $notices, true);
        $this->buildLiteral('title', $source_data, $dataset, $notices);
        $this->buildLiteral('description', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('authority', 'DONL:Organization', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('publisher', 'DONL:Organization', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('sourceCatalog', 'DONL:Catalogs', $source_data, $dataset, $notices);
        $this->buildContactPoint('contactPoint', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('license', 'DONL:License', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('metadataLanguage', 'DONL:Language', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('language', 'DONL:Language', $source_data, $dataset, $notices, true);
        $this->buildControlledVocabularyEntry('accessRights', 'Overheid:Openbaarheidsniveau', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('datasetStatus', 'Overheid:DatasetStatus', $source_data, $dataset, $notices);
        $this->buildControlledVocabularyEntry('theme', 'Overheid:Taxonomiebeleidsagenda', $source_data, $dataset, $notices, true);
        $this->buildDateTime('modificationDate', $source_data, $dataset, $notices);
        $this->buildLiteral('keyword', $source_data, $dataset, $notices, true);
        $this->buildURI('landingPage', $source_data, $dataset, $notices);
        $this->buildSpatial('spatial', $source_data, $dataset, $notices, true);
        $this->buildTemporal('temporal', $source_data, $dataset, $notices);
        $this->buildURI('conformsTo', $source_data, $dataset, $notices, true);
        $this->buildURI('relatedResource', $source_data, $dataset, $notices, true);
        $this->buildURI('source', $source_data, $dataset, $notices, true);
        $this->buildURI('hasVersion', $source_data, $dataset, $notices, true);
        $this->buildURI('isVersionOf', $source_data, $dataset, $notices, true);
        $this->buildDateTime('releaseDate', $source_data, $dataset, $notices);
        $this->buildLiteral('version', $source_data, $dataset, $notices);
        $this->buildLiteral('versionNotes', $source_data, $dataset, $notices, true);
        $this->buildLegalFoundation('legalFoundation', $source_data, $dataset, $notices);
        $this->buildDateTime('datePlanned', $source_data, $dataset, $notices);
        $this->buildURI('documentation', $source_data, $dataset, $notices, true);
        $this->buildControlledVocabularyEntry('frequency', 'Overheid:Frequency', $source_data, $dataset, $notices);
        $this->buildURI('provenance', $source_data, $dataset, $notices, true);
        $this->buildURI('sample', $source_data, $dataset, $notices, true);
        $this->buildBoolean('highValue', $source_data, $dataset, $notices);
        $this->buildBoolean('referentieData', $source_data, $dataset, $notices);
        $this->buildBoolean('basisRegister', $source_data, $dataset, $notices);
        $this->buildBoolean('nationalCoverage', $source_data, $dataset, $notices);
        $this->buildDistribution('resources', $source_data, $dataset, $notices, true);

        $container = new DatasetContainer();
        $container->setCatalogName($catalog_name);
        $container->setCatalogIdentifier($source_data['identifier']);
        $container->setDataset($dataset);
        $container->setConversionNotices($notices);
        $container->generateHash();

        return $container;
    }

    /**
     * Getter for the default_values property.
     *
     * @return DefaultMapper|null The default_values property
     */
    public function getDefaultValues(): ?DefaultMapper
    {
        return $this->default_values;
    }

    /**
     * Getter for the value_mappings property.
     *
     * @return ValueMapper[] The value_mappings property
     */
    public function getValueMappings(): array
    {
        return $this->value_mappings;
    }

    /**
     * Getter for the blacklist_mappings property.
     *
     * @return BlacklistMapper[] The blacklist_mappings property
     */
    public function getBlacklistMappings(): array
    {
        return $this->blacklist_mappings;
    }

    /**
     * Getter for the whitelist_mappings property.
     *
     * @return WhitelistMapper[] The whitelist_mappings property
     */
    public function getWhitelistMappings(): array
    {
        return $this->whitelist_mappings;
    }

    /**
     * Getter for the custom_dataset_build_rules property.
     *
     * @return IDCATEntityBuildRule[] The custom_build_rules property
     */
    public function getCustomDatasetBuildRules(): array
    {
        return $this->custom_dataset_build_rules;
    }

    /**
     * Getter for the custom_distribution_build_rules property.
     *
     * @return IDCATEntityBuildRule[] The custom_build_rules property
     */
    public function getCustomDistributionBuildRules(): array
    {
        return $this->custom_distribution_build_rules;
    }

    /**
     * Setter for the default_values property.
     *
     * @param DefaultMapper $default_values The value to set
     */
    public function setDefaultValues(DefaultMapper $default_values): void
    {
        $this->default_values = $default_values;
    }

    /**
     * Setter for the value_mappings property.
     *
     * @param ValueMapper[] $value_mappings The value to set
     */
    public function setValueMappings(array $value_mappings): void
    {
        $this->value_mappings = $value_mappings;
    }

    /**
     * Setter for the blacklist_mappings property.
     *
     * @param BlacklistMapper[] $blacklist_mappings The value to set
     */
    public function setBlacklistMappings(array $blacklist_mappings): void
    {
        $this->blacklist_mappings = $blacklist_mappings;
    }

    /**
     * Setter for the whitelist_mappings property.
     *
     * @param WhitelistMapper[] $whitelist_mappings The value to set
     */
    public function setWhitelistMappings(array $whitelist_mappings): void
    {
        $this->whitelist_mappings = $whitelist_mappings;
    }

    /**
     * Setter for the custom_dataset_build_rules property.
     *
     * @param IDCATEntityBuildRule[] $custom_build_rules The value to set
     */
    public function setCustomDatasetBuildRules(array $custom_build_rules): void
    {
        $this->custom_dataset_build_rules = $custom_build_rules;
    }

    /**
     * Setter for the custom_distribution_build_rules property.
     *
     * @param IDCATEntityBuildRule[] $custom_build_rules The value to set
     */
    public function setCustomDistributionBuildRules(array $custom_build_rules): void
    {
        $this->custom_distribution_build_rules = $custom_build_rules;
    }

    /**
     * Builds a Dataset property.
     *
     * @param string               $property     The property to build
     * @param string               $build_rule   The fully qualified classname of the buildrule to
     *                                           apply
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param string               $prefix       The logging prefix to use
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildProperty(string $property, string $build_rule, array &$source_data,
                                   DCATDataset &$dataset, array &$notices, string $prefix,
                                   bool $multi_valued = false): void
    {
        if (array_key_exists($property, $this->custom_dataset_build_rules)) {
            $build_rule = $this->custom_dataset_build_rules[$property];
        } else {
            $build_rule = new $build_rule($property, $prefix);
            $build_rule->setDefaults($this->default_values);
            $build_rule->setValueMappers($this->value_mappings);
            $build_rule->setBlacklists($this->blacklist_mappings);
            $build_rule->setWhitelists($this->whitelist_mappings);
        }

        if ($multi_valued) {
            $entities = $build_rule->buildMultiple($source_data, $notices);

            if (count($entities) > 0) {
                $method = 'add' . ucfirst($property);

                foreach ($entities as $entity) {
                    $dataset->$method($entity);
                }
            }
        } else {
            $entity = $build_rule->build($source_data, $notices);

            if (null !== $entity) {
                $method = 'set' . ucfirst($property);
                $dataset->$method($entity);
            }
        }
    }

    /**
     * Attempts to build a DCATURI for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildURI(string $property, array &$source_data, DCATDataset &$dataset,
                              array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATURIBuildRule::class, $source_data, $dataset, $notices,
            'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATLiteral for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildLiteral(string $property, array &$source_data, DCATDataset &$dataset,
                                  array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATLiteralBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATControlledVocabularyEntry for the given property.
     *
     * @param string               $property     The property to build
     * @param string               $vocabulary   The vocabulary of the DCATControlledVocabularyEntry
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildControlledVocabularyEntry(string $property, string $vocabulary,
                                                    array &$source_data, DCATDataset &$dataset,
                                                    array &$notices,
                                                    bool $multi_valued = false): void
    {
        if (array_key_exists($property, $this->custom_dataset_build_rules)) {
            $build_rule = $this->custom_dataset_build_rules[$property];
        } else {
            $build_rule = new DCATControlledVocabularyEntryBuildRule(
                $property, 'Dataset', $vocabulary
            );
            $build_rule->setDefaults($this->default_values);
            $build_rule->setValueMappers($this->value_mappings);
            $build_rule->setBlacklists($this->blacklist_mappings);
            $build_rule->setWhitelists($this->whitelist_mappings);
        }

        if ($multi_valued) {
            $entities = $build_rule->buildMultiple($source_data, $notices);

            if (count($entities) > 0) {
                $method = 'add' . ucfirst($property);

                foreach ($entities as $entity) {
                    $dataset->$method($entity);
                }
            }
        } else {
            $entity = $build_rule->build($source_data, $notices);

            if (null !== $entity) {
                $method = 'set' . ucfirst($property);
                $dataset->$method($entity);
            }
        }
    }

    /**
     * Attempts to build a DCATDateTime for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildDateTime(string $property, array &$source_data, DCATDataset &$dataset,
                                   array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATDateTimeBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATBoolean for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildBoolean(string $property, array &$source_data, DCATDataset &$dataset,
                                  array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATBooleanBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATContactPoint for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildContactPoint(string $property, array &$source_data, DCATDataset &$dataset,
                                       array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATContactPointBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATSpatial for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildSpatial(string $property, array &$source_data, DCATDataset &$dataset,
                                  array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATSpatialBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATTemporal for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildTemporal(string $property, array &$source_data, DCATDataset &$dataset,
                                   array &$notices, bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATTemporalBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATLegalFoundation for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildLegalFoundation(string $property, array &$source_data,
                                          DCATDataset &$dataset, array &$notices,
                                          bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, DCATLegalFoundationBuildRule::class, $source_data, $dataset,
            $notices, 'Dataset', $multi_valued
        );
    }

    /**
     * Attempts to build a DCATDistribution for the given property.
     *
     * @param string               $property     The property to build
     * @param array<string, mixed> $source_data  The harvested data from the catalog
     * @param DCATDataset          $dataset      The dataset being constructed
     * @param string[]             $notices      The notices generated during execution so far
     * @param bool                 $multi_valued How many elements to build for the property
     */
    private function buildDistribution(string $property, array &$source_data, DCATDataset &$dataset,
                                       array &$notices, bool $multi_valued = false): void
    {
        if (array_key_exists($property, $this->custom_dataset_build_rules)) {
            /**
             * Typehint 'hack' for proper autocomplete/static analysis.
             *
             * @var DONLDistributionBuildRule $build_rule
             */
            $build_rule = $this->custom_dataset_build_rules[$property];
        } else {
            $build_rule = new DONLDistributionBuildRule($property, 'Distribution');
            $build_rule->setDefaults($this->default_values);
            $build_rule->setValueMappers($this->value_mappings);
            $build_rule->setBlacklists($this->blacklist_mappings);
            $build_rule->setWhitelists($this->whitelist_mappings);
            $build_rule->setCustomBuildRules($this->custom_distribution_build_rules);
        }

        if ($multi_valued) {
            $entities = $build_rule->buildMultiple($source_data, $notices);

            if (0 === count($entities)) {
                return;
            }

            foreach ($entities as $entity) {
                $dataset->addDistribution($entity);
            }
        } else {
            $entity = $build_rule->build($source_data, $notices);

            if (null !== $entity) {
                $method = 'set' . ucfirst($property);
                $dataset->$method($entity);
            }
        }
    }
}
