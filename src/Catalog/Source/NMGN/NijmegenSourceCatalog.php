<?php

namespace DonlSync\Catalog\Source\NMGN;

use DonlSync\Application;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NMGN\BuildRule\NijmegenBuildRuleFactory;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class NijmegenSourceCatalog.
 *
 * Represents the Nijmegen open data catalog.
 */
class NijmegenSourceCatalog implements ISourceCatalog
{
    /** @var string */
    private $catalog_name;

    /** @var string */
    private $catalog_endpoint;

    /** @var string[] */
    private $credentials;

    /** @var int */
    private $ckan_request_limit;

    /** @var array */
    private $ckan_field_mapping;

    /** @var Client */
    private $api_client;

    /** @var BuilderConfiguration */
    private $builder_config;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, Application $application)
    {
        try {
            $this->catalog_name       = $config->get('catalog_name');
            $this->catalog_endpoint   = $config->get('catalog_endpoint');
            $this->credentials        = $application->ckan_credentials($this->catalog_name);
            $this->api_client         = $application->guzzle_client($config->get('api_base_path'));
            $this->ckan_request_limit = $application->config('ckan')->get('api_row_count');
            $this->ckan_field_mapping = $config->get('ckan_field_mapping');

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
    public function getCredentials(): array
    {
        return $this->credentials;
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
                $request = $this->api_client->post('api/3/action/package_search', [
                    'json'    => [
                        'q'               => 'sync_to:data.overheid.nl',
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
    public function getDatasetBuildRules(): array
    {
        return NijmegenBuildRuleFactory::getAllDatasetBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return NijmegenBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
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
     * @param array $harvest The harvested data
     *
     * @return array The transformed data
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
