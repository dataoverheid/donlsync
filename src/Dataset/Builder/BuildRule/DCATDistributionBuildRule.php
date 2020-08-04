<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATEntity;
use DonlSync\Dataset\Builder\DatasetBuilder;
use DonlSync\Dataset\DONLDistribution;
use DonlSync\Dataset\Mapping\DefaultMapper;

/**
 * Class DCATDistributionBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATDistribution` object.
 *
 * @see \DCAT_AP_DONL\DCATDistribution
 */
class DCATDistributionBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /** @var IDCATEntityBuildRule[] */
    protected $custom_build_rules;

    /**
     * [@inheritdoc}.
     */
    public function __construct(string $property, string $prefix = 'Dataset')
    {
        parent::__construct($property, $prefix);

        $this->custom_build_rules = [];
    }

    /**
     * Assigns custom build rules to use during construction.
     *
     * @param IDCATEntityBuildRule[] $custom_build_rules The custom build rules
     */
    public function setCustomBuildRules(array $custom_build_rules): void
    {
        $this->custom_build_rules = $custom_build_rules;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        // single distribution builder not supported (yet?).

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $max                = $_ENV['CATALOG_TARGET_DISTRIBUTION_MAX'];
        $dcat_distributions = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_distributions;
        }

        if (count($data[$this->property]) > $max) {
            $data[$this->property] = array_slice($data[$this->property], 0, $max);

            $notices[] = sprintf('Dataset: Distribution count exceeded limit %s; kept first %s',
                $max, $max
            );
        }

        for ($i = 1; $i < count($data[$this->property]) + 1; ++$i) {
            if (count($notices) > 0) {
                $notices[] = '---';
            }

            $index        = $i - 1;
            $distribution = new DONLDistribution();

            $this->buildLiteral('title', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildLiteral('description', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildURI('accessURL', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildURI('downloadURL', $data[$this->property][$index], $distribution, $notices, $i, DatasetBuilder::MULTI_VALUED);
            $this->buildControlledVocabularyEntry('distributionType', 'DONL:DistributionType', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildControlledVocabularyEntry('license', 'DONL:License', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildControlledVocabularyEntry('metadataLanguage', 'DONL:Language', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildControlledVocabularyEntry('language', 'DONL:Language', $data[$this->property][$index], $distribution, $notices, $i, DatasetBuilder::MULTI_VALUED);
            $this->buildControlledVocabularyEntry('format', 'MDR:FiletypeNAL', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildControlledVocabularyEntry('mediaType', 'IANA:Mediatypes', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildLiteral('rights', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildControlledVocabularyEntry('status', 'ADMS:Distributiestatus', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildDateTime('releaseDate', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildDateTime('modificationDate', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildNumber('byteSize', $data[$this->property][$index], $distribution, $notices, $i);
            $this->buildURI('linkedSchemas', $data[$this->property][$index], $distribution, $notices, $i, DatasetBuilder::MULTI_VALUED);
            $this->buildURI('documentation', $data[$this->property][$index], $distribution, $notices, $i, DatasetBuilder::MULTI_VALUED);
            $this->buildChecksum('checksum', $data[$this->property][$index], $distribution, $notices, $i);

            $validation_result = $distribution->validate();

            if (!$validation_result->validated()) {
                foreach ($validation_result->getMessages() as $message) {
                    $notices[] = sprintf('%s %s: %s', $this->prefix, $i, ucfirst($message));
                }

                $notices[] = sprintf('%s %s: Failed validation, discarding',
                    $this->prefix, $i
                );

                continue;
            }

            $notices[] = sprintf('%s %s: Valid, adding to dataset', $this->prefix, $i);

            $dcat_distributions[] = $distribution;
        }

        return $dcat_distributions;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults(DefaultMapper $defaults): void
    {
        $resource_defaults = [];
        $defaults_mapper   = new DefaultMapper();

        foreach ($defaults->getFullMap() as $property => $default) {
            if ('resource.' === mb_substr($property, 0, mb_strlen('resource.'))) {
                $resource_defaults[mb_substr($property, mb_strlen('resource.'))] = $default;
            }
        }

        $defaults_mapper->setMap($resource_defaults);

        $this->defaults = $defaults_mapper;
    }

    /**
     * {@inheritdoc}
     */
    public function setValueMappers(array $mappers): void
    {
        $this->value_mappers = $this->extractResourceSettings($mappers);
    }

    /**
     * {@inheritdoc}
     */
    public function setBlacklists(array $mappers): void
    {
        $this->blacklists = $this->extractResourceSettings($mappers);
    }

    /**
     * {@inheritdoc}
     */
    public function setWhitelists(array $mappers): void
    {
        $this->whitelists = $this->extractResourceSettings($mappers);
    }

    /**
     * Extracts the resource mappings from the catalog mappings.
     *
     * @param array $mappings The mappings to extract the resource mappings from
     *
     * @return array The settings for resources
     */
    private function extractResourceSettings(array $mappings): array
    {
        $resource_settings = [];

        foreach ($mappings as $key => $mapping) {
            if ('resource.' === mb_substr($key, 0, mb_strlen('resource.'))) {
                $resource_settings[mb_substr($key, mb_strlen('resource.'))] = $mapping;
            }
        }

        return $resource_settings;
    }

    /**
     * Attempts to build a DCATLiteral for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param string           $build_rule   The fully qualified classname of the buildrule
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param string           $prefix       The logging prefix
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildProperty(string $property, array &$source_data, string $build_rule,
                                   DONLDistribution &$distribution, array &$notices, string $prefix,
                                   bool $multi_valued = false): void
    {
        if (array_key_exists($property, $this->custom_build_rules)) {
            $build_rule = $this->custom_build_rules[$property];
            $build_rule->setPrefix($prefix);
        } else {
            $build_rule = new $build_rule($property, $prefix);
        }

        $build_rule->setDefaults($this->defaults);
        $build_rule->setValueMappers($this->value_mappers);
        $build_rule->setBlacklists($this->blacklists);
        $build_rule->setWhitelists($this->whitelists);

        if ($multi_valued) {
            $entities = $build_rule->buildMultiple($source_data, $notices);

            if (count($entities) > 0) {
                $method = 'add' . ucfirst($property);

                foreach ($entities as $entity) {
                    $distribution->$method($entity);
                }
            }
        } else {
            $entity = $build_rule->build($source_data, $notices);

            if (null !== $entity) {
                $method = 'set' . ucfirst($property);
                $distribution->$method($entity);
            }
        }
    }

    /**
     * Attempts to build a DCATLiteral for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildLiteral(string $property, array &$source_data,
                                  DONLDistribution &$distribution, array &$notices, int $iterator,
                                  bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, $source_data, DCATLiteralBuildRule::class, $distribution,
            $notices, 'Distribution ' . $iterator, $multi_valued
        );
    }

    /**
     * Attempts to build a DCATURI for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildURI(string $property, array &$source_data,
                              DONLDistribution &$distribution, array &$notices, int $iterator,
                              bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, $source_data, DCATURIBuildRule::class, $distribution,
            $notices, 'Distribution ' . $iterator, $multi_valued
        );
    }

    /**
     * Attempts to build a DCATDateTime for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildDateTime(string $property, array &$source_data,
                                   DONLDistribution &$distribution, array &$notices, int $iterator,
                                   bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, $source_data, DCATDateTimeBuildRule::class, $distribution,
            $notices, 'Distribution ' . $iterator, $multi_valued
        );
    }

    /**
     * Attempts to build a DCATNumber for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildNumber(string $property, array &$source_data,
                                 DONLDistribution &$distribution, array &$notices, int $iterator,
                                 bool $multi_valued = false): void
    {
        $this->buildProperty(
            $property, $source_data, DCATNumberBuildRule::class, $distribution,
            $notices, 'Distribution ' . $iterator, $multi_valued
        );
    }

    /**
     * Attempts to build a DCATControlledVocabularyEntry for the given property.
     *
     * @param string           $property     The property to build
     * @param string           $vocabulary   The vocabulary of the property
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildControlledVocabularyEntry(string $property, string $vocabulary,
                                                    array &$source_data,
                                                    DONLDistribution &$distribution,
                                                    array &$notices, int $iterator,
                                                    bool $multi_valued = false): void
    {
        if (array_key_exists($property, $this->custom_build_rules)) {
            $build_rule = $this->custom_build_rules[$property];
            $build_rule->setPrefix('Distribution ' . $iterator);
        } else {
            $build_rule = new DCATControlledVocabularyEntryBuildRule(
                $property, 'Distribution ' . $iterator, $vocabulary
            );
        }

        $build_rule->setDefaults($this->defaults);
        $build_rule->setValueMappers($this->value_mappers);
        $build_rule->setBlacklists($this->blacklists);
        $build_rule->setWhitelists($this->whitelists);

        if ($multi_valued) {
            $entities = $build_rule->buildMultiple($source_data, $notices);

            if (count($entities) > 0) {
                $method = 'add' . ucfirst($property);

                foreach ($entities as $entity) {
                    $distribution->$method($entity);
                }
            }
        } else {
            $entity = $build_rule->build($source_data, $notices);

            if (null !== $entity) {
                $method = 'set' . ucfirst($property);
                $distribution->$method($entity);
            }
        }
    }

    /**
     * Attempts to build a DCATChecksum for the given property.
     *
     * @param string           $property     The property to build
     * @param array            $source_data  The harvested data from the catalog
     * @param DONLDistribution $distribution The dataset being constructed
     * @param string[]         $notices      The notices generated during execution so far
     * @param int              $iterator     The nth distribution being built
     * @param bool             $multi_valued How many elements to build for the property
     */
    private function buildChecksum(string $property, array &$source_data,
                                   DONLDistribution &$distribution, array &$notices, int $iterator,
                                   bool $multi_valued = false): void
    {
        // checksum builder not implemented (yet?)
    }
}
