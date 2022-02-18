<?php

namespace DonlSync\Catalog\Source\StelselCatalogus\Concept;

use DonlSync\ApplicationInterface;
use DonlSync\Configuration;
use DonlSync\Exception\ConceptHarvestingException;
use DonlSync\Exception\ConfigurationException;

/**
 * Interface IConceptHarvester.
 *
 * Contract for harvesting concepts
 */
interface IConceptHarvester
{
    /**
     * IConceptHarvester constructor.
     *
     * @param Configuration        $config      The configuration of this harvester
     * @param ApplicationInterface $application The application for getting application variables
     *
     * @throws ConfigurationException When the concept harvested cannot be constructed
     */
    public function __construct(Configuration $config, ApplicationInterface $application);

    /**
     * Gets all concepts for the given dataset id.
     *
     * @param string $dataset_sc_id The dataset id
     *
     * @throws ConceptHarvestingException When anything goes wrong during getting concepts
     *                                    from the external source
     *
     * @return array<int, mixed> The array of concepts for this dataset
     */
    public function getConcepts(string $dataset_sc_id): array;

    /**
     * Gets schema for the given concept id.
     *
     * @param string $concept_id The concept id
     *
     * @throws ConceptHarvestingException When anything goes wrong during getting the schema
     *                                    from the external source
     *
     * @return array<int, mixed> The schema of this concept
     */
    public function getSchema(string $concept_id): array;
}
