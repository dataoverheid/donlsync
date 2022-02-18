<?php

namespace DonlSync\Catalog\Source\CKAN;

use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class CKANCatalog.
 *
 * Abstract class for CKAN source catalogs that implements common functionality.
 */
abstract class CKANCatalog implements ISourceCatalog
{
    /**
     * The configuration that should be given to the builder. This configuration instructs the
     * builder how to construct datasets from the data harvested from this catalog.
     */
    protected BuilderConfiguration $builder_config;
    /**
     * The Guzzle client for interacting with the catalog API.
     */
    private Client $api_client;

    /**
     * The mapping from source field to DCAT field.
     *
     * @var array<string, array>
     */
    private array $ckan_field_mapping;

    /**
     * The credentials to use when sending the harvested datasets to the target catalog.
     *
     * @var array<string, string>
     */
    private array $credentials;

    /**
     * The amount of objects to retrieve from the CKAN API per request. CKAN has a hard limit on
     * 1000 objects per request. This property should not exceed that value.
     */
    private int $ckan_request_limit;

    /**
     * The CKAN query to use when getting al data.
     */
    private string $ckan_query;

    /**
     * The name of the catalog.
     */
    private string $catalog_name;

    /**
     * The URL of the catalog.
     */
    private string $catalog_endpoint;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name       = $config->get('catalog_name');
            $this->catalog_endpoint   = $config->get('catalog_endpoint');
            $this->credentials        = $application->ckan_credentials($this->catalog_name);
            $this->ckan_field_mapping = $config->get('ckan_field_mapping');
            $this->api_client         = $application->guzzle_client($config->get('api_base_path'));
            $this->ckan_request_limit = $application->config('ckan')->get('api_row_count');
            $this->ckan_query         = $config->get('ckan_query');

            $this->builder_config = BuilderConfiguration::loadBuilderConfigurations(
                $config, $application->guzzle_client(), $application->mapping_loader()
            );
        } catch (ConfigurationException | MappingException $e) {
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
        try {
            $objects = [];
            $offset  = 0;

            do {
                $request = $this->api_client->get('api/3/action/package_search', [
                    'query'    => [
                        'q'               => $this->ckan_query,
                        'include_private' => false,
                        'rows'            => $this->ckan_request_limit,
                        'start'           => $offset,
                    ],
                ]);
                $response = json_decode($request->getBody()->getContents(), true);

                if (true == !$response['success']) {
                    throw new CatalogHarvestingException('CKAN API request failure.');
                }

                $count           = $response['result']['count'];
                $got_all_objects = $count <= $offset + $this->ckan_request_limit;
                $offset          = $offset + $this->ckan_request_limit;

                foreach ($response['result']['results'] as $harvest) {
                    $objects[] = $this->mapHarvestKeys($harvest);
                }
            } while (!$got_all_objects);

            return $objects;
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(): array
    {
        return $this->credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogSlugName(): string
    {
        return $this->catalog_name;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogEndpoint(): string
    {
        return $this->catalog_endpoint;
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderConfig(): BuilderConfiguration
    {
        return $this->builder_config;
    }

    /**
     * Transforms the harvested data to ensure the proper array keys are used.
     *
     * @param array<string, mixed> $harvest The harvested data
     *
     * @return array<string, mixed> The transformed data
     */
    private function mapHarvestKeys(array $harvest): array
    {
        $mapped_harvest       = [];
        $dataset_key_mapping  = $this->ckan_field_mapping['dataset'];
        $resource_key_mapping = $this->ckan_field_mapping['resource'];

        foreach ($dataset_key_mapping as $source => $target) {
            if (array_key_exists($source, $harvest) && !empty($harvest[$source])) {
                $mapped_harvest[$target] = $harvest[$source];
            }
        }

        foreach ($harvest['tags'] as $tag) {
            $mapped_harvest['keyword'][] = $tag['name'];
        }

        foreach ($harvest['resources'] as $resource) {
            $mapped_resource = [];

            foreach ($resource_key_mapping as $source => $target) {
                if (array_key_exists($source, $resource)) {
                    $mapped_resource[$target] = $resource[$source];
                }
            }

            $mapped_harvest['resources'][] = $mapped_resource;
        }

        return $mapped_harvest;
    }
}
