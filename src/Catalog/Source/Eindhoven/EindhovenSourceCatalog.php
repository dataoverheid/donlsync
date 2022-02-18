<?php

namespace DonlSync\Catalog\Source\Eindhoven;

use DateTime;
use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\Eindhoven\BuildRule\EindhovenBuildRuleFactory;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use DonlSync\Helper\StringHelper;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Class EindhovenSourceCatalog.
 *
 * Represents the Eindhoven open data catalog.
 */
class EindhovenSourceCatalog implements ISourceCatalog
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
     * The pattern to use for constructing the DCAT identifier property of a dataset.
     */
    private string $id_pattern;

    /**
     * The DCAT configuration data.
     */
    private Configuration $dcat_config;

    /**
     * The mapping data to map harvested fields to their DCAT dataset counterparts.
     *
     * @var array<string, string>
     */
    private array $dataset_mapping;

    /**
     * The mapping data to map harvested fields to their DCAT distribution counterparts.
     *
     * @var array<string, string>
     */
    private array $distribution_mapping;

    /**
     * The fields to copy from the harvested datasets to their accompanying distributions.
     *
     * @var array<string, string>
     */
    private array $distribution_inheritance_mapping;

    /**
     * Which harvested metadata to use for multiple DCAT properties for a distribution.
     *
     * @var array<string, string>
     */
    private array $distribution_copy_fields;

    /**
     * The configuration for interacting with the Eindhoven API.
     *
     * @var array<string, mixed>
     */
    private array $api_config;

    /**
     * The template to use for generation DataSchema distributions of an Eindhoven dataset.
     *
     * @var array<string, mixed>
     */
    private array $dataschema_preset;

    /**
     * The HTML to Markdown converter.
     */
    private HtmlConverter $html_converter;

    /**
     * {@inheritdoc}
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name                     = $config->get('catalog_name');
            $this->catalog_endpoint                 = $config->get('catalog_endpoint');
            $this->credentials                      = $application->ckan_credentials($this->catalog_name);
            $this->api_client                       = $application->guzzle_client($config->get('api_base_path'));
            $this->id_pattern                       = $config->get('identifier_pattern');
            $this->dcat_config                      = $application->config('dcat');
            $this->dataset_mapping                  = $config->get('dataset')['field_mapping'];
            $this->distribution_mapping             = $config->get('distribution')['field_mapping'];
            $this->distribution_inheritance_mapping = $config->get('distribution')['inheritance'];
            $this->distribution_copy_fields         = $config->get('distribution')['copy_fields'];
            $this->api_config                       = $config->get('api');
            $this->dataschema_preset                = $config->get('dataschema');
            $this->html_converter                   = new HtmlConverter(['strip_tags' => true]);

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
    public function getData(): array
    {
        try {
            $response     = $this->api_client->request('GET', 'data.json');
            $raw_datasets = json_decode($response->getBody()->getContents(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new CatalogHarvestingException(
                    'Received uninterpretable JSON from Eindhoven catalog'
                );
            }

            if (!array_key_exists('dataset', $raw_datasets)) {
                throw new CatalogHarvestingException(
                    'Eindhoven catalog exposed no datasets via API'
                );
            }

            return array_map(function ($dataset) {
                return $this->transformHarvestedDataset($dataset);
            }, $raw_datasets['dataset']);
        } catch (RequestException | GuzzleException $e) {
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
    public function getDatasetBuildRules(): array
    {
        return EindhovenBuildRuleFactory::getAllDatasetBuildRules(
            $this->builder_config, $this->dcat_config
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return EindhovenBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderConfig(): BuilderConfiguration
    {
        return $this->builder_config;
    }

    /**
     * Transforms the harvested dataset into a form that can be processed by the DatasetBuilder.
     *
     * @param array<string, mixed> $dataset The harvested dataset
     *
     * @throws CatalogHarvestingException Thrown on any error when extracting the dataschema of the
     *                                    dataset
     *
     * @return array<string, mixed> The transformed dataset ready to be consumed
     */
    private function transformHarvestedDataset(array $dataset): array
    {
        if (!array_key_exists('rights', $dataset)) {
            // TODO:
            // DatasetBuilder has issues if the 'license' key is not present in the harvest. Not
            // sure why, requires additional debugging. Setting it to an empty string results in a
            // similar error.
            $dataset['rights'] = 'unknown';
        }

        if (!empty($dataset['references']) && is_array($dataset['references'])) {
            // Some harvested references values do not have the HTTP protocol specified. Pass the
            // value through the repairURL method to correct this.
            $dataset['references'] = array_map(function ($element) {
                return StringHelper::repairURL((string) $element);
            }, $dataset['references']);
        }

        $transformed = $this->applyFieldMapping($dataset, [], $this->dataset_mapping);

        if (empty($transformed['identifier'])) {
            return $transformed;
        }

        $transformed['identifier'] = sprintf($this->id_pattern, $transformed['identifier']);

        if (array_key_exists('modificationDate', $transformed)) {
            try {
                $transformed['modificationDate'] = (new DateTime($transformed['modificationDate']))
                    ->format('Y-m-d\TH:i:s');
            } catch (Exception $e) {
                // Source date is invalid, ignore. DatasetBuilder will reject and log the property.
            }
        }

        $transformed['resources'] = $this->transformHarvestedDistributions($dataset);

        $transformed = $this->convertHTMLtoMDForHarvestedDataset($transformed);

        if (!empty($dataschema = $this->createDataschemaResource($transformed, $dataset['identifier']))) {
            $transformed['resources'][] = $dataschema;
        }

        return $transformed;
    }

    /**
     * Converts HTML tags in the description fields of a dataset and its distributions to MarkDown.
     *
     * @param array<string, mixed> $dataset The dataset to convert HTML tags from
     *
     * @return array<string, mixed> The dataset with HTML converted to MarkDown
     */
    private function convertHTMLtoMDForHarvestedDataset(array $dataset): array
    {
        if (array_key_exists('description', $dataset)) {
            $dataset['description'] = $this->html_converter->convert($dataset['description']);
        }

        $dataset['resources'] = array_map(function (array $resource): array {
            if (array_key_exists('description', $resource)) {
                $resource['description'] = $this->html_converter->convert($resource['description']);
            }

            return $resource;
        }, $dataset['resources']);

        return $dataset;
    }

    /**
     * Transforms the list schema of a given harvested dataset to a distribution.
     *
     * @param array<string, mixed> $harvest    The harvested dataset to get the schema from
     * @param string               $identifier The id of the dataset (without the URL prefix)
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return array<string, mixed> The schema in a distribution array
     */
    private function createDataschemaResource(array $harvest, string $identifier): array
    {
        try {
            $response = $this->api_client->get($this->api_config['requests']['schemas'] . '/' . $identifier);
            $response = json_decode($response->getBody()->getContents(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new CatalogHarvestingException(
                    'Received uninterpretable JSON from Eindhoven catalog'
                );
            }

            if (!array_key_exists('dataset', $response)) {
                return [];
            }

            $dataset = $response['dataset'];

            if (!array_key_exists('fields', $dataset) || empty($dataset['fields'])) {
                return [];
            }

            $table = array_map(function (array $field): array {
                return [
                    'name'        => $field['label'] ?? null,
                    'code'        => $field['name'] ?? null,
                    'type'        => $field['type'] ?? null,
                    'description' => $field['description'] ?? null,
                ];
            }, $dataset['fields']);

            return array_merge($this->dataschema_preset, [
                'title'       => $harvest['title'] . ' - Schema' ?? null,
                'description' => json_encode($table) ?? null,
                'license'     => $harvest['license'] ?? null,
                'accessURL'   => $harvest['identifier'] ?? null,
                'language'    => $harvest['language'] ?? [],
            ]);
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e);
        }
    }

    /**
     * Transforms a harvested distributions so that they can be used in the dataset import process.
     *
     * @param array<string, mixed> $dataset The harvested dataset
     *
     * @return array<int, array> The transformed distributions of the dataset
     */
    private function transformHarvestedDistributions(array $dataset): array
    {
        if (!array_key_exists('distribution', $dataset)) {
            return [];
        }

        $harvest = [];

        foreach ($dataset['distribution'] as $distribution) {
            $transformed = [
                'accessURL' => $this->getDistributionURL($distribution),
            ];

            $transformed = $this->applyFieldMapping($distribution, $transformed, $this->distribution_mapping);
            $transformed = $this->inheritDatasetFields($dataset, $transformed);
            $transformed = $this->copyFields($transformed, $this->distribution_copy_fields);

            $harvest[] = $transformed;
        }

        return $harvest;
    }

    /**
     * Applies a field mapping to the harvested data so that the data can be used in the Dataset
     * importing procedure.
     *
     * @param array<string, mixed>  $harvest     The harvested data from the catalog
     * @param array<string, mixed>  $transformed The transformed data representing the harvest
     * @param array<string, string> $mapping     The {source_key} => {target_key} field mapping
     *
     * @return array<string, mixed> The $transformed array with the applied field mapping from
     *                       $harvest to $transformed
     */
    private function applyFieldMapping(array $harvest, array $transformed, array $mapping): array
    {
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $harvest)) {
                $transformed[$target] = $harvest[$source];
            }
        }

        return $transformed;
    }

    /**
     * Determine which property to use as the AccessURL of a distribution. Some datasets expose an
     * AccessURL, while others expose only a DownloadURL.
     *
     * @param array<string, mixed> $distribution The distribution to determine the URL for
     *
     * @return string|null The URL to use as the AccessURL, or null if none could be determined
     */
    private function getDistributionURL(array $distribution): ?string
    {
        if (array_key_exists('accessURL', $distribution)) {
            return $distribution['accessURL'];
        }

        if (array_key_exists('downloadURL', $distribution)) {
            return $distribution['downloadURL'];
        }

        return null;
    }

    /**
     * Inherits harvested properties from a dataset into one of its harvested distributions.
     *
     * @param array<string, mixed> $dataset      The harvested dataset from the catalog
     * @param array<string, mixed> $distribution One of the distributions of the harvested dataset
     *
     * @return array<string, mixed> The original distribution including any inherited fields
     */
    private function inheritDatasetFields(array $dataset, array $distribution): array
    {
        foreach ($this->distribution_inheritance_mapping as $source => $target) {
            if (!array_key_exists($source, $dataset)) {
                continue;
            }

            if (array_key_exists($target, $distribution)) {
                continue;
            }

            $distribution[$target] = $dataset[$source];
        }

        return $distribution;
    }

    /**
     * Copies fields from one array to another based on the given {source_key} => {target_key}
     * mapping. No action is taken when.
     *
     * - The sourceKey is not present in the source
     * - The targetKey is already present in the target
     *
     * @param array<string, mixed>  $harvest The array containing the fields to copy
     * @param array<string, string> $mapping The {source_key} => {target_key} mapping array
     *
     * @return array<string, mixed> The original array including the copied fields
     */
    private function copyFields(array $harvest, array $mapping): array
    {
        foreach ($mapping as $source => $target) {
            if (!array_key_exists($source, $harvest)) {
                continue;
            }

            if (array_key_exists($target, $harvest)) {
                continue;
            }

            $harvest[$target] = $harvest[$source];
        }

        return $harvest;
    }
}
