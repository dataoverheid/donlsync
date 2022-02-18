<?php

namespace DonlSync\Catalog\Source\RDW;

use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\RDW\BuildRule\RDWBuildRuleFactory;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class RDWSourceCatalog.
 *
 * Represents the RDW open data catalog.
 */
class RDWSourceCatalog implements ISourceCatalog
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
     * The credentials to use when sending the harvested datasets to the target catalog.
     *
     * @var array<string, string>
     */
    private array $credentials;

    /**
     * The Guzzle client for interacting with the catalog API.
     */
    private Client $api_client;

    /**
     * The configuration that should be given to the builder. This configuration instructs the
     * builder how to construct datasets from the data harvested from this catalog.
     */
    private BuilderConfiguration $builder_config;

    /**
     * The DCAT configuration data.
     */
    private Configuration $dcat_config;

    /**
     * The 'timestring' to append to any datetime fields to ensure that valid datetime objects can
     * be constructed.
     */
    private string $date_appendage;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name     = $config->get('catalog_name');
            $this->catalog_endpoint = $config->get('catalog_endpoint');
            $this->credentials      = $application->ckan_credentials($this->catalog_name);
            $this->api_client       = $application->guzzle_client($config->get('api_base_path'));
            $this->dcat_config      = $application->config('dcat');
            $this->date_appendage   = $this->dcat_config->get('datetime_appendage');

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
            $response        = $this->api_client->get('data.json');
            $parsed_response = json_decode($response->getBody()->getContents(), true);

            if (null == $parsed_response) {
                throw new CatalogHarvestingException('Malformed JSON response');
            }

            $entities = [];

            foreach ($parsed_response['dataset'] as $record) {
                $entities[] = $this->extractDataset($record);
            }

            return $entities;
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDatasetBuildRules(): array
    {
        return RDWBuildRuleFactory::getAllDatasetBuildRules(
            $this->builder_config, $this->dcat_config
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return RDWBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderConfig(): BuilderConfiguration
    {
        return $this->builder_config;
    }

    /**
     * Attempts to extract a potential dataset from a given array of data.
     *
     * @param array<string, mixed> $record The data from which to extract a dataset
     *
     * @return array<string, mixed> The potential dataset
     */
    private function extractDataset(array $record): array
    {
        $extracted = [];

        $fields = [
            'identifier'  => 'identifier',
            'title'       => 'title',
            'description' => 'description',
            'modified'    => 'modificationDate',
            'issued'      => 'releaseDate',
            'landingPage' => 'landingPage',
        ];

        foreach ($fields as $key => $value) {
            if (isset($record[$key])) {
                $extracted[$value] = $record[$key];
            }
        }

        if (array_key_exists('modificationDate', $extracted)) {
            $extracted['modificationDate'] = $extracted['modificationDate'] . $this->date_appendage;
        }

        if (array_key_exists('releaseDate', $extracted)) {
            $extracted['releaseDate'] = $extracted['releaseDate'] . $this->date_appendage;
        }

        $arrayFields = [
            'conforms_to' => 'conformsTo',
            'theme'       => 'theme',
        ];

        foreach ($arrayFields as $key => $value) {
            if (isset($record[$key])) {
                foreach ($record[$key] as $element) {
                    $extracted[$value][] = $element;
                }
            }
        }

        if (isset($record['keyword'])) {
            foreach ($record['keyword'] as $keyword) {
                $extracted['keyword'][] = preg_replace('|/|', ' ', $keyword);
            }
        }

        if (isset($record['accessLevel'])) {
            $extracted['accessRights'] = $record['accessLevel'];
        }

        if (isset($record['contactPoint']['fn'])) {
            $extracted['contact_point_name'] = $record['contactPoint']['fn'];
        }

        $extracted['resources'] = [];

        if (array_key_exists('distribution', $record) && is_array($record['distribution'])) {
            foreach ($record['distribution'] as $resource) {
                $distribution = [];

                if (isset($resource['mediaType'])) {
                    $distribution['title']     = $resource['mediaType'];
                    $distribution['format']    = $resource['mediaType'];
                    $distribution['mediaType'] = $resource['mediaType'];
                }

                if (isset($resource['downloadURL'])) {
                    $distribution['accessURL'] = $resource['downloadURL'];
                }

                if (isset($record['description'])) {
                    $distribution['description'] = $record['description'];
                }

                $extracted['resources'][] = $distribution;
            }
        }

        return $extracted;
    }
}
