<?php

namespace DonlSync\Catalog\Target\DONL;

use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Configuration;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Dataset\DatasetTransformer;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\CatalogPublicationException;
use DonlSync\Exception\ConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class DONLTargetCatalog.
 *
 * Represents a CKAN application running the ckanext-dataoverheid extension.
 */
class DONLTargetCatalog implements ITargetCatalog
{
    /**
     * The name of the catalog.
     */
    private string $catalog_name;

    /**
     * The URL of the catalog.
     */
    private string $catalog_endpoint;

    /**
     * The Guzzle client for interacting with the catalog API.
     */
    private Client $api_client;

    /**
     * The list of properties, per object, which should not be touched by this application when
     * updating a dataset on the target catalog.
     *
     * @var array<string, array>
     */
    private array $persistent_properties;

    /**
     * The CKAN configuration data.
     */
    private Configuration $ckan_config;

    /**
     * The transformer responsible for transforming the DCAT objects into valid CKAN objects so that
     * they can be send to the CKAN API.
     */
    private DatasetTransformer $dataset_transformer;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name          = $config->get('catalog_name');
            $this->catalog_endpoint      = $config->get('catalog_endpoint');
            $this->persistent_properties = $config->get('persistent_properties');
            $this->ckan_config           = $application->config('ckan');
            $this->dataset_transformer   = new DatasetTransformer($this->ckan_config);
            $this->api_client            = $application->guzzle_client(
                $config->get('api_base_path')
            );
        } catch (ConfigurationException $e) {
            throw new CatalogInitializationException(
                $e->getMessage(), 0, $e
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
    public function getPersistentProperties(): array
    {
        return $this->persistent_properties;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $credentials = []): array
    {
        Configuration::checkKeys($credentials, ITargetCatalog::CREDENTIAL_KEYS);

        $objects = [];
        $offset  = 0;

        do {
            try {
                $limit    = $this->ckan_config->get('api_row_count');
                $response = $this->api_client->post('api/3/action/package_search', [
                    'headers' => ['Authorization' => $credentials['api_key']],
                    'json'    => [
                        'q'     => 'creator_user_id:' . $credentials['user_id'],
                        'rows'  => $limit,
                        'start' => $offset,
                    ],
                ]);
                $response = json_decode($response->getBody()->getContents(), true);

                if (null === $response) {
                    throw new CatalogHarvestingException(
                        'Catalog response cannot be parsed as JSON'
                    );
                }

                if (false === $response['success']) {
                    throw new CatalogHarvestingException(
                        'API request to harvest data failed'
                    );
                }

                $count           = $response['result']['count'];
                $got_all_objects = $count <= $offset + $limit;
                $offset          = $offset + $limit;

                $objects = array_merge($objects, $response['result']['results']);
            } catch (RequestException | ConfigurationException $e) {
                throw new CatalogHarvestingException($e->getMessage());
            }
        } while (!$got_all_objects);

        return $objects;
    }

    /**
     * {@inheritdoc}
     *
     * Performs an `api/3/action/package_create` API call to CKAN.
     */
    public function publishDataset(DatasetContainer $container, array $credentials): string
    {
        Configuration::checkKeys($credentials, ITargetCatalog::CREDENTIAL_KEYS);

        try {
            $dataset = $this->dataset_transformer->transform($container->getDataset());

            $dataset['donlsync_checksum'] = $container->getDatasetHash();
            $dataset['owner_org']         = $credentials['owner_org'];
            $dataset['name']              = $this->generateName(
                $container->getAssignedNumber(),
                $container->getDataset()->getTitle()->getData()
            );

            $response = $this->api_client->post('api/3/action/package_create', [
                'headers' => ['Authorization' => $credentials['api_key']],
                'json'    => $dataset,
            ]);

            $data = json_decode($response->getBody(), true);

            if (null === $data) {
                throw new CatalogPublicationException(
                    'Unknown result of package_create operation; Invalid JSON response'
                );
            }

            if (false === $data['success']) {
                throw new CatalogPublicationException($data['error']['message']);
            }

            return $data['result']['id'];
        } catch (RequestException $e) {
            throw new CatalogPublicationException($e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage()
            );
        } catch (ConfigurationException $e) {
            throw new CatalogPublicationException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     *
     * Performs an `api/3/action/package_update` API call to CKAN to update a given dataset.
     */
    public function updateDataset(DatasetContainer $container, array $credentials): void
    {
        Configuration::checkKeys($credentials, ITargetCatalog::CREDENTIAL_KEYS);

        try {
            $dataset = $this->dataset_transformer->transform($container->getDataset());

            $dataset['donlsync_checksum'] = $container->getDatasetHash();
            $dataset['id']                = $container->getTargetIdentifier();

            // TODO:
            // Disabled feature until analysis reveals why certain resources are omitted from the
            // dataset object prior to it being sent to the CKAN installation.
            //$this->persistProperties($dataset, $container->getTargetIdentifier());

            $response = $this->api_client->post('api/3/action/package_update', [
                'headers' => ['Authorization' => $credentials['api_key']],
                'json'    => $dataset,
            ]);
            $response = json_decode($response->getBody()->getContents(), true);

            if (null === $response) {
                throw new CatalogPublicationException(
                    'Unknown result of package_update operation; Invalid JSON response'
                );
            }

            if (false === $response['success']) {
                throw new CatalogPublicationException($response['error']['message']);
            }
        } catch (RequestException $e) {
            throw new CatalogPublicationException($e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage()
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Performs an `api/3/action/dataset_purge` API call to CKAN to permanently delete the dataset
     * with the given id.
     */
    public function deleteDataset(string $id, array $credentials): void
    {
        Configuration::checkKeys($credentials, ITargetCatalog::CREDENTIAL_KEYS);

        try {
            $response = $this->api_client->post('api/3/action/dataset_purge', [
                'headers' => ['Authorization' => $credentials['api_key']],
                'json'    => ['id' => $id],
            ]);
            $response = json_decode($response->getBody()->getContents(), true);

            if (null === $response) {
                throw new CatalogPublicationException(
                    'Unknown result of dataset_purge operation; Invalid JSON response'
                );
            }

            if (false === $response['success']) {
                throw new CatalogPublicationException($response['error']['message']);
            }
        } catch (RequestException $e) {
            throw new CatalogPublicationException($e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage()
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Performs an `api/3/action/package_show` API call to CKAN to request the dataset with the
     * given id. It returns the contents of the JSON key 'result' which should be present in the
     * response of the mentioned API call.
     */
    public function getDataset(string $id): array
    {
        try {
            $response = $this->api_client->post('api/3/action/package_show', [
                'json' => ['id' => $id],
            ]);
            $response = json_decode($response->getBody()->getContents(), true);

            if (null === $response) {
                throw new CatalogPublicationException(
                    'Unknown result of package_show operation; Invalid JSON response'
                );
            }

            if (false === $response['success']) {
                throw new CatalogPublicationException($response['error']['message']);
            }

            return $response['result'];
        } catch (RequestException $e) {
            throw new CatalogPublicationException($e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage()
            );
        }
    }

    /**
     * Generates a valid name based on the CKAN validation rules based on a given title and unique
     * number.
     *
     * @param int    $assigned_number The number assigned to the dataset
     * @param string $title           The title of the dataset
     *
     * @throws ConfigurationException On any configuration error
     *
     * @return string The generated name
     */
    private function generateName(int $assigned_number, string $title): string
    {
        $name_config = $this->ckan_config->get('name');

        $name = $assigned_number . '-' . mb_strtolower(
            preg_replace($name_config['regex_pattern'], $name_config['regex_replacement'], $title)
        );

        if (mb_strlen($name) > $name_config['max_length']) {
            $name = mb_substr($name, 0, $name_config['max_length']);
        }

        return $name;
    }

    /**
     * Persists certain configurable properties for a given dataset. This means that the properties
     * which are defined as "persistent" will not be modified by the DonlSync application.
     *
     * @param array<string, mixed> $dataset           The dataset for which properties must be persisted
     * @param string               $target_identifier The identifier of the dataset on the target catalog
     *
     * @throws CatalogPublicationException Thrown if the dataset could not be retrieved from the
     *                                     target catalog
     */
    private function persistProperties(array &$dataset, string $target_identifier): void
    {
        try {
            $current_dataset = $this->getDataset($target_identifier);

            foreach ($this->persistent_properties['dataset'] as $persistent_prop) {
                if (array_key_exists($persistent_prop, $current_dataset)) {
                    $dataset[$persistent_prop] = $current_dataset[$persistent_prop];
                }
            }

            if (empty($dataset['resources'])) {
                return;
            }

            foreach ($dataset['resources'] as &$resource) {
                if (!array_key_exists('name', $resource)) {
                    continue;
                }

                foreach ($current_dataset['resources'] as $current_resource) {
                    $title_match  = $current_resource['name'] === $resource['name'];
                    $link_match   = $current_resource['url'] === $resource['url'];
                    $format_match = $current_resource['format'] === $resource['format'];

                    if ($title_match && $link_match && $format_match) {
                        $resource['id'] = $current_resource['id'];
                    }
                }
            }

            foreach ($this->persistent_properties['resource'] as $persistent_prop) {
                foreach ($current_dataset['resources'] as $current_resource) {
                    foreach ($dataset['resources'] as &$dataset_resource) {
                        if (!array_key_exists('id', $dataset_resource)) {
                            continue;
                        }

                        if ($dataset_resource['id'] === $current_resource['id']) {
                            if (array_key_exists($persistent_prop, $current_resource)) {
                                $dataset_resource[$persistent_prop] = $current_resource[$persistent_prop];
                            }
                        }
                    }
                }
            }
        } catch (CatalogPublicationException $e) {
            throw new CatalogPublicationException(
                'Unable to persist properties; failed to retrieve dataset from catalog', 1, $e
            );
        }
    }
}
