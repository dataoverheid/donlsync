<?php

namespace DonlSync\Catalog\Source\ODataCatalog;

use DOMNode;
use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\ODataCatalog\BuildRule\ODataCatalogBuildRuleFactory;
use DonlSync\Catalog\Source\ODataCatalog\Tools\ODataCatalogXMLMetadataExtractor;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use DonlSync\Helper\StringHelper as SH;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ODataCatalog.
 *
 * Represents a ODataCatalog portal such as the portal of CBS or CBSDerden.
 */
class ODataCatalog implements ISourceCatalog
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
     * The Guzzle client for interacting with the catalog theme API.
     */
    private Client $theme_client;

    /**
     * The configuration that should be given to the builder. This configuration instructs the
     * builder how to construct datasets from the data harvested from this catalog.
     */
    private BuilderConfiguration $builder_config;

    /**
     * The XPath queries per field for harvesting the metadata of said field.
     *
     * @var array<string, mixed>
     */
    private array $xpath;

    /**
     * The prefix to use when constructing identifier properties of harvested datasets.
     */
    private string $identifier_prefix;

    /**
     * The DCAT configuration data.
     */
    private Configuration $dcat_config;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name      = $config->get('catalog_name');
            $this->catalog_endpoint  = $config->get('catalog_endpoint');
            $this->credentials       = $application->ckan_credentials($this->catalog_name);
            $this->api_client        = $application->guzzle_client($config->get('api_base_path'));
            $this->theme_client      = $application->guzzle_client();
            $this->xpath             = $config->get('xpath_selectors');
            $this->identifier_prefix = $config->get('identifier_prefix');
            $this->dcat_config       = $application->config('dcat');

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
            $response  = $this->api_client->get('Tables');
            $harvest   = [];
            $extractor = new ODataCatalogXMLMetadataExtractor($response->getBody(), $this->xpath);

            foreach ($extractor->query($this->xpath['entries']) as $entry) {
                $dataset = $this->extractDataset($extractor, $entry);

                if (null === $dataset) {
                    continue;
                }

                $harvest[] = $dataset;
            }

            return $harvest;
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDatasetBuildRules(): array
    {
        return ODataCatalogBuildRuleFactory::getAllDatasetBuildRules(
            $this->builder_config, $this->dcat_config
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return ODataCatalogBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderConfig(): BuilderConfiguration
    {
        return $this->builder_config;
    }

    /**
     * Extracts a dataset from the XML response.
     *
     * @param ODataCatalogXMLMetadataExtractor $extractor The XML extractor
     * @param DOMNode                          $entry     The node holding the individual dataset
     *
     * @return array<string, mixed>|null The extracted dataset or null if none were found
     */
    private function extractDataset(ODataCatalogXMLMetadataExtractor $extractor,
                                    DOMNode $entry): ?array
    {
        try {
            $dataset   = [
                'cbs_id'           => $extractor->datasetField('id', $entry),
                'cbs_url_lang'     => $extractor->datasetField('language', $entry),
                'identifier'       => $extractor->datasetField('id', $entry),
                'title'            => $extractor->datasetField('title', $entry),
                'description'      => $extractor->datasetField('description', $entry),
                'modificationDate' => $extractor->datasetField('modificationDate', $entry),
                'authority'        => $extractor->datasetField('authority', $entry),
                'landingPage'      => $extractor->datasetField('landingPage', $entry),
                'language'         => $extractor->datasetField('language', $entry, true),
                'metadataLanguage' => $extractor->datasetField('metadataLanguage', $entry),
                'frequency'        => $extractor->datasetField('frequency', $entry),
                'theme'            => $extractor->datasetField('identifier', $entry),
            ];

            $dataset['identifier'] = $this->identifier_prefix . $dataset['identifier'];
            $dataset['theme']      = $this->loadThemesFor($dataset['theme']);

            $resources = [
                'API'  => 'api_url',
                'Feed' => 'feed_url',
            ];

            foreach ($resources as $name => $field) {
                $resource = $extractor->resourceField($field, $entry);

                if ('' === $resource) {
                    continue;
                }

                $dataset['resources'][] = [
                    'cbs_id'           => $extractor->resourceField('id', $entry),
                    'cbs_url_lang'     => $extractor->resourceField('language', $entry),
                    'accessURL'        => $extractor->resourceField('accessURL', $entry),
                    'downloadURL'      => [SH::ltrim($resource, 'http://', 'https://')],
                    'title'            => $name,
                    'format'           => $name,
                    'mediaType'        => $name,
                    'description'      => $extractor->resourceField('description', $entry),
                    'language'         => $extractor->resourceField('language', $entry, true),
                    'metadataLanguage' => $extractor->resourceField('metadataLanguage', $entry),
                ];
            }

            return $dataset;
        } catch (CatalogHarvestingException $e) {
            return null;
        }
    }

    /**
     * Loads the themes from the ODataCatalog API for the specified dataset.
     *
     * @param string $identifier The identifier of the dataset
     *
     * @throws CatalogHarvestingException On a non-existent $xpath_key or $field
     *
     * @return string[] The arrays for the dataset
     */
    private function loadThemesFor(string $identifier): array
    {
        try {
            $response = $this->theme_client->get($identifier . '/Themes');
            $xpath    = new ODataCatalogXMLMetadataExtractor($response->getBody(), $this->xpath);

            return $xpath->datasetField('theme', null, true);
        } catch (RequestException $e) {
            return [];
        }
    }
}
