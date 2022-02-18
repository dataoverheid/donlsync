<?php

namespace DonlSync\Catalog\Source\StelselCatalogus\Concept;

use DonlSync\ApplicationInterface;
use DonlSync\Configuration;
use DonlSync\Exception\ConceptHarvestingException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Class StelselCatalogusConceptHarvester.
 *
 * This is used for harvesting concepts and schemas for Stelsel Catalogus
 */
class StelselCatalogusConceptHarvester implements IConceptHarvester
{
    /**
     * The HTTP client to communicate with concepts/schema API.
     */
    private ClientInterface $http_client;

    /**
     * The API path for getting concepts.
     */
    private string $concept_path;

    /**
     * The API path for getting schemas.
     */
    private string $schema_path;

    /**
     * The mapping from schemas in SC API to schemas in DONL.
     *
     * @var array<string, string> The target-key => source-key mapping
     */
    private array $schema_mapping;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        $this->http_client    = $application->guzzle_client($config->get('concept_base_path'));
        $this->concept_path   = $config->get('concept_path');
        $this->schema_path    = $config->get('schema_path');
        $this->schema_mapping = $config->get('schema_mapping');
    }

    /**
     * {@inheritdoc}
     */
    public function getConcepts(string $dataset_sc_id): array
    {
        try {
            $response = $this->http_client->request('GET', $this->concept_path, [
                'query' => ['registraties' => $dataset_sc_id],
            ]);

            $response = json_decode($response->getBody()->getContents(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new ConceptHarvestingException('Received invalid JSON');
            }

            return $response;
        } catch (RequestException | GuzzleException $e) {
            throw new ConceptHarvestingException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(string $concept_id): array
    {
        try {
            $response = $this->http_client->request('GET', $this->schema_path, [
                'query' => ['begrippen' => $concept_id],
            ]);

            $response = json_decode($response->getBody()->getContents(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new ConceptHarvestingException('Received invalid JSON');
            }

            return array_map(function (array $row) {
                return [
                    'name'        => $row[$this->schema_mapping['name']] ?? null,
                    'code'        => null,
                    'type'        => null,
                    'description' => $row[$this->schema_mapping['description']] ?? null,
                    'legend'      => null,
                ];
            }, $response);
        } catch (RequestException | GuzzleException $e) {
            throw new ConceptHarvestingException($e->getMessage());
        }
    }
}
