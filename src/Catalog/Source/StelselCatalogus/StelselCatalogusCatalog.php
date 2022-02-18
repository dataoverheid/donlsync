<?php

namespace DonlSync\Catalog\Source\StelselCatalogus;

use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\CKAN\CKANCatalog;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\StelselCatalogus\Concept\StelselCatalogusConceptHarvester;
use DonlSync\Configuration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConceptHarvestingException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Helper\StringHelper;

/**
 * Class StelselCatalogusCatalog.
 *
 * This class harvests Stelsel Catalogus datasets and adds dataschemas harvested from a different
 * source
 */
class StelselCatalogusCatalog extends CKANCatalog implements ISourceCatalog
{
    /**
     * An harvester that harvests concepts and schemas from Stelsel Catalogus.
     */
    private StelselCatalogusConceptHarvester $sc_concept_harvester;

    /**
     * The pattern the Stelsel Catalogus identifiers have.
     */
    private string $sc_identifier_pattern;

    /**
     * The template to use for generation DataSchema distributions of an SC dataset.
     *
     * @var array<string, mixed>
     */
    private array $dataschema_preset;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->sc_identifier_pattern = $config->get('identifier_pattern');
            $this->sc_concept_harvester  = new StelselCatalogusConceptHarvester($config, $application);
            $this->dataschema_preset     = $config->get('dataschema');

            parent::__construct($config, $application);
        } catch (ConfigurationException $e) {
            throw new CatalogInitializationException(
                'failed to initialize catalog; ' . $e->getMessage(), 0, $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        $datasets = array_map([$this, 'addSchema'], parent::getData());

        return array_filter($datasets, function ($dataset) {
            return !empty($dataset['resources']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getDatasetBuildRules(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return [];
    }

    /**
     * Given the harvested dataset, add the data schema for each concept of this dataset.
     *
     * @param array<string, mixed> $harvest The harvested dataset
     *
     * @throws CatalogHarvestingException When anything goes when adding the schema distributions
     *                                    to the SC harvested datasets
     *
     * @return array<string, mixed> The harvested dataset, including the data schemas for each concept
     */
    private function addSchema(array $harvest): array
    {
        if (null === ($sc_identifier = self::extract_sc_identifier($harvest))) {
            return $harvest;
        }

        $harvest['resources'] = array_key_exists('resources', $harvest)
            ? array_filter($harvest['resources'], function (array $resource): bool {
                return !self::isDataSchemaResource($resource);
            })
            : [];

        $harvest['resources'] = array_merge(
            $harvest['resources'],
            $this->getConceptDistributions($harvest, $sc_identifier)
        );

        return $harvest;
    }

    /**
     * Determines whether the given resource is a data schema resource.
     *
     * @param array<string, mixed> $resource The resource to examine
     *
     * @return bool True when the given resource is a data schema resource else return false
     */
    private static function isDataSchemaResource(array $resource): bool
    {
        return array_key_exists('distribution_type', $resource)
            && 'https://data.overheid.nl/distributiontype/dataschema' === $resource['distribution_type'];
    }

    /**
     * Given a Stelsel Catalogus dataset id, return the distributions that represent a data schema.
     *
     * @param array<string, mixed> $harvest       The harvested dataset, which is used for some defaults in the distribution
     * @param string               $dataset_sc_id The Stelsel Catalogus identifier
     *
     * @throws CatalogHarvestingException When getting the schema from a different source fails
     *
     * @return array<int, array<string, mixed>> Returns for each concept a distribution
     */
    private function getConceptDistributions(array $harvest, string $dataset_sc_id): array
    {
        try {
            $concepts              = $this->sc_concept_harvester->getConcepts($dataset_sc_id);
            $concept_distributions = [];

            foreach ($concepts as $concept) {
                if (!empty($schema = $this->sc_concept_harvester->getSchema($concept['id']))) {
                    $concept_distributions[] = array_merge($this->dataschema_preset, [
                        'title'             => $concept['prefLabel'],
                        'description'       => json_encode($schema),
                        'license'           => $harvest['license'],
                        'accessURL'         => StringHelper::repairURL($concept['id']),
                        'language'          => $harvest['language'],
                        'metadataLanguage'  => $harvest['metadataLanguage'],
                    ]);
                }
            }

            return $concept_distributions;
        } catch (ConceptHarvestingException $e) {
            throw new CatalogHarvestingException($e);
        }
    }

    /**
     * Extracts the Stelsel Catalogus identifier from the "alternateIdentifier" field.
     *
     * @param array<string, mixed> $harvest The harvested dataset
     *
     * @return string|null Returns the SC identifier or null of there is none
     */
    private function extract_sc_identifier(array $harvest): ?string
    {
        if (!array_key_exists('alternateIdentifier', $harvest)) {
            return null;
        }

        foreach ($harvest['alternateIdentifier'] as $alternate_identifier) {
            if (1 === preg_match($this->sc_identifier_pattern, $alternate_identifier)) {
                return preg_replace('/^https/', 'http', $alternate_identifier);
            }
        }

        return null;
    }
}
