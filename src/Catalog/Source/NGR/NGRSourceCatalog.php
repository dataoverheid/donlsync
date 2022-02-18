<?php

namespace DonlSync\Catalog\Source\NGR;

use DOMElement;
use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NGR\BuildRule\NGRBuildRuleFactory;
use DonlSync\Catalog\Source\NGR\Tools\NGRXMLMetadataExtractor;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use DonlSync\Helper\StringHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class NGRSourceCatalog.
 *
 * Represents the NGR open data catalog.
 */
class NGRSourceCatalog implements ISourceCatalog
{
    /**
     * The 'ideal' length of a harvested datetime property.
     *
     * @var int
     */
    private const DATE_LENGTH_MATCH = 19;

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
     * The configuration for interacting with the NGR API.
     *
     * @var array<string, mixed>
     */
    private array $api_config;

    /**
     * The validation rules for creating valid tags according to CKAN.
     *
     * @var array<string, mixed>
     */
    private array $tag_config;

    /**
     * The XPath queries per field for harvesting the metadata of said field.
     *
     * @var array<string, mixed>
     */
    private array $xpaths;

    /**
     * The DCAT configuration data.
     */
    private Configuration $dcat_config;

    /**
     * The 'timestring' to append to datetime properties.
     */
    private string $time_appendage;

    /**
     * The pattern to use for constructing the DCAT identifier property of a dataset.
     */
    private string $id_pattern;

    /**
     * The pattern to use for constructing the link to a Feature Catalog Description.
     */
    private string $schema_pattern;

    /**
     * The template to use for generation Visualization distributions of a NGR dataset.
     *
     * @var array<string, mixed>
     */
    private array $visualization_preset;

    /**
     * The template to use for generation DataSchema distributions of an NGR dataset.
     *
     * @var array<string, mixed>
     */
    private array $dataschema_preset;

    /**
     * Whether to harvest any exposed bounding box geo metadata from the datasets.
     */
    private bool $harvest_bounding_box;

    /**
     * {@inheritdoc}
     *
     * The following array keys are expected in `$config`:
     * - catalog_name
     * - catalog_endpoint
     * - api_base_path
     * - mappings
     * - api_id_request_size
     * - api_dataset_request_size
     */
    public function __construct(Configuration $config, ApplicationInterface $application)
    {
        try {
            $this->catalog_name         = $config->get('catalog_name');
            $this->catalog_endpoint     = $config->get('catalog_endpoint');
            $this->credentials          = $application->ckan_credentials($this->catalog_name);
            $this->api_client           = $application->guzzle_client($config->get('api_base_path'));
            $this->api_config           = $config->get('api');
            $this->xpaths               = $config->get('xpath_selectors');
            $this->tag_config           = $application->config('ckan')->get('tags');
            $this->dcat_config          = $application->config('dcat');
            $this->time_appendage       = $this->dcat_config->get('datetime_appendage');
            $this->id_pattern           = $config->get('identifier_pattern');
            $this->schema_pattern       = $config->get('schema_url_pattern');
            $this->visualization_preset = $config->get('visualization');
            $this->dataschema_preset    = $config->get('dataschema');
            $this->harvest_bounding_box = $config->get('harvest_bounding_box');

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
     *
     * The harvesting process procedure is as follows:
     * - Get the amount of datasets from the catalog
     * - With this information all the ids of the datasets are retrieved
     * - For each batch of ids the datasets are harvested and parsed
     *
     * This process is split into these steps and batches as to not overload the NGR catalog.
     */
    public function getData(): array
    {
        $dataset_count_on_ngr = $this->getDatasetCountFromCatalog();
        $dataset_ids          = [];
        $id_rows              = $this->api_config['id_rows'];

        if (0 === $dataset_count_on_ngr) {
            throw new CatalogHarvestingException('no datasets found on NGR');
        }

        if ($dataset_count_on_ngr < $id_rows) {
            $id_rows = $dataset_count_on_ngr;
        }

        for ($i = 1; $i <= $dataset_count_on_ngr; $i = $i + $id_rows) {
            $dataset_ids = array_merge(
                $dataset_ids,
                $this->getDatasetIDsFromCatalog($i, $i + $id_rows - 1)
            );
        }

        $datasets              = [];
        $dataset_ids_in_chunks = array_chunk($dataset_ids, $this->api_config['dataset_rows']);

        foreach ($dataset_ids_in_chunks as $id_chunk) {
            $datasets = array_merge($datasets, $this->getDatasetsByIDRange($id_chunk));
        }

        return $datasets;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatasetBuildRules(): array
    {
        return NGRBuildRuleFactory::getAllDatasetBuildRules(
            $this->builder_config, $this->dcat_config
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDistributionBuildRules(): array
    {
        return NGRBuildRuleFactory::getAllDistributionBuildRules($this->builder_config);
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderConfig(): BuilderConfiguration
    {
        return $this->builder_config;
    }

    /**
     * Determines the accurate dataset count from the NGR catalog.
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return int The dataset count
     */
    private function getDatasetCountFromCatalog(): int
    {
        try {
            $api_call         = $this->api_config['requests']['dataset_count'];
            $response         = $this->api_client->get($api_call);
            $response_as_json = json_decode($response->getBody(), true);

            if (null == $response_as_json) {
                throw new CatalogHarvestingException('malformed JSON received from NGR');
            }

            return $response_as_json['summary']['@count'];
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e->getMessage());
        }
    }

    /**
     * Harvests the dataset IDs from the NGR catalog. This is done in chunks as the NGR API can't
     * handle serving all the IDs in a single request.
     *
     * @param int $lower_bound The lower bound index of the dataset ids
     * @param int $upper_bound The upped bound index of the dataset ids
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return string[] The harvested ids
     */
    private function getDatasetIDsFromCatalog(int $lower_bound, int $upper_bound): array
    {
        try {
            $dataset_ids = [];
            $response    = $this->api_client->get(sprintf(
                $this->api_config['requests']['dataset_ids'], $lower_bound, $upper_bound
            ));

            $response_as_json = json_decode($response->getBody(), true);

            if (null === $response_as_json) {
                throw new CatalogHarvestingException('malformed JSON received from NGR');
            }

            foreach ($response_as_json['metadata'] as $record) {
                $dataset_ids[] = 1 === count($record) ? $record[0]['uuid'] : $record['uuid'];
            }

            return $dataset_ids;
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e->getMessage());
        }
    }

    /**
     * Retrieve specific datasets from the NGR catalog based on their IDs.
     *
     * @param array<mixed, string|array> $ids The ids of the datasets to harvest
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return array<int, array> The harvested datasets
     */
    private function getDatasetsByIDRange(array $ids): array
    {
        try {
            $filter_contents = '';
            $filter_format   = file_get_contents(__DIR__ . '/Assets/filter.xml');

            foreach ($ids as $id) {
                if (is_array($id)) {
                    continue;
                }

                $filter_contents .= sprintf($filter_format, 'Identifier', $id);
            }

            if (count($ids) > 1) {
                $filter_contents = '<ogc:Or>' . $filter_contents . '</ogc:Or>';
            }

            $request_body = file_get_contents(__DIR__ . '/Assets/request.xml');
            $request_body = sprintf($request_body, 1, count($ids), $filter_contents);

            $datasets = [];
            $response = $this->api_client->post($this->api_config['requests']['datasets'], [
                'headers' => ['Content-Type' => 'text/xml; charset=UTF8'],
                'body'    => $request_body,
            ]);

            for ($iterator = 0; $iterator < count($ids); ++$iterator) {
                $datasets[] = $this->extractData($response->getBody(), $iterator + 1);
            }

            return $datasets;
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e);
        }
    }

    /**
     * Extracts the metadata of a dataset from the XML response.
     *
     * @param string $xml   The XML response
     * @param int    $index The nth dataset to extract metadata for
     *
     * @throws CatalogHarvestingException When attempting to harvest data without XPath mapping
     *
     * @return array<string, mixed> The extracted metadata
     */
    private function extractData(string $xml, int $index): array
    {
        $extractor  = new NGRXMLMetadataExtractor($xml, $this->xpaths);
        $harvest    = [
            'language'           => $extractor->datasetField('language', $index, true),
            'license'            => $extractor->getDatasetLicense($index),
            'theme'              => $extractor->datasetField('theme', $index, true),
            'modificationDate'   => $extractor->getModificationDate($index),
            'conformsTo'         => $extractor->datasetField('conformsTo', $index, true),
        ];

        if ($this->harvest_bounding_box) {
            $harvest['coordinates'] = $extractor->getSpatialCoordinates($index);
        }

        $fields = [
            'identifier', 'landingPage', 'title', 'description', 'metadataLanguage', 'authority',
            'publisher', 'contact_point_email', 'contact_point_phone', 'contact_point_webpage',
            'contact_point_name', 'releaseDate', 'temporal_start', 'temporal_end',
        ];

        foreach ($fields as $field) {
            $harvest[$field] = $extractor->datasetField($field, $index);
        }

        $harvest['identifier']            = sprintf($this->id_pattern, $harvest['identifier']);
        $harvest['landingPage']           = StringHelper::repairURL($harvest['landingPage']);
        $harvest['contact_point_webpage'] = StringHelper::repairURL($harvest['contact_point_webpage']);
        $harvest['modificationDate']      = $this->repairDateTime($harvest['modificationDate']);
        $harvest['releaseDate']           = $this->repairDateTime($harvest['releaseDate']);
        $harvest['temporal_start']        = $this->repairDateTime($harvest['temporal_start']);
        $harvest['temporal_end']          = $this->repairDateTime($harvest['temporal_end']);

        foreach ($extractor->datasetField('keyword', $index, true) as $keyword) {
            $keyword = $this->repairKeyword($keyword);

            if (mb_strlen($keyword) > $this->tag_config['max_length']) {
                continue;
            }

            if (mb_strlen($keyword) < $this->tag_config['min_length']) {
                continue;
            }

            $harvest['keyword'][] = $keyword;
        }

        $resources = $extractor->query(sprintf($this->xpaths['dataset']['distribution'][0],
            $index)
        );

        foreach ($resources as $distribution) {
            $resource = [
                'license'  => $extractor->getDatasetLicense($index),
                'language' => $extractor->resourceField('language', $distribution, $index, true),
            ];

            $fields = [
                'accessURL', 'title', 'description', 'format', 'mediaType', 'metadataLanguage',
            ];

            foreach ($fields as $field) {
                $resource[$field] = $extractor->resourceField($field, $distribution, $index);
            }

            $resource['accessURL'] = StringHelper::repairURL($resource['accessURL']);

            if ('' === $resource['format']) {
                $resource['format'] = pathinfo($resource['accessURL'])['extension'] ?? '';
            }

            if ('' === $resource['title']) {
                $resource['title'] = $resource['format'];
            }

            $harvest['resources'][] = $resource;
        }

        if (!empty($schema_id = $extractor->datasetField('schema_id', $index))) {
            $harvest['resources'][] = $this->createDataschemaResource($harvest, $schema_id);
        }

        if (!empty($graphic = $extractor->datasetField('graphic', $index))) {
            if (basename($graphic) !== $graphic) {
                $harvest['resources'][] = $this->createVisualizationResource($graphic, $harvest);
            }
        }

        return $harvest;
    }

    /**
     * Generates a DCAT distribution with type Visualization based on the harvested URL pointing to
     * a graphical representation of a dataset.
     *
     * @param string               $graphicURL The harvested URL
     * @param array<string, mixed> $harvest    The dataset harvest
     *
     * @return array<string, mixed> The generated visualization distribution
     */
    private function createVisualizationResource(string $graphicURL, array $harvest): array
    {
        $url = StringHelper::repairURL($graphicURL);

        // TODO:
        // Find a reliable way to determine the appropriate format.
        if (false !== mb_strpos(mb_strtolower($url), 'jpg')) {
            $format = 'http://publications.europa.eu/resource/authority/file-type/JPEG';
        } else {
            $format = 'http://publications.europa.eu/resource/authority/file-type/PNG';
        }

        return array_merge($this->visualization_preset, [
            'accessURL' => $url,
            'format'    => $format,
            'license'   => $harvest['license'],
        ]);
    }

    /**
     * Transforms the list schema of a given harvested dataset to a distribution.
     *
     * @param array<string, mixed> $harvest   The harvested dataset to get the schema from
     * @param string               $schema_id The id of the schema
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return array<string, mixed> The schema in a distribution array
     */
    private function createDataschemaResource(array $harvest, string $schema_id): array
    {
        try {
            $response = $this->api_client->get($this->api_config['requests']['schemas'], [
                'query' => [
                    'request'        => 'GetRecordById',
                    'service'        => 'CSW',
                    'version'        => '2.0.2',
                    'elementSetName' => 'full',
                    'outputSchema'   => 'http://www.isotc211.org/2005/gfc',
                    'id'             => $schema_id,
                ],
            ]);

            $extractor  = new NGRXMLMetadataExtractor($response->getBody(), $this->xpaths);
            $attributes = $extractor->query($this->xpaths['schema']['attributes'][0]);

            $table = array_map(function (DOMElement $attribute) use ($extractor): array {
                return [
                    'name'        => $extractor->schemaField('attribute_name', false, $attribute),
                    'code'        => $extractor->schemaField('attribute_code', false, $attribute),
                    'type'        => $extractor->schemaField('attribute_type', false, $attribute),
                    'description' => $extractor->schemaField('attribute_description', false, $attribute),
                    'legend'      => $this->createLegendTable($extractor, $attribute),
                ];
            }, iterator_to_array($attributes));

            return array_merge($this->dataschema_preset, [
                'title'            => $extractor->schemaField('title'),
                'description'      => json_encode($table),
                'license'          => $harvest['license'],
                'accessURL'        => sprintf($this->schema_pattern, $schema_id),
                'language'         => $harvest['language'],
                'metadataLanguage' => $harvest['metadataLanguage'],
            ]);
        } catch (RequestException $e) {
            throw new CatalogHarvestingException($e);
        }
    }

    /**
     * Creates the legend table for an attribute.
     *
     * @param NGRXMLMetadataExtractor $extractor The extractor to use to extract data from the XML
     * @param DOMElement              $attribute The attribute for which we are creating the legend table
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return array<string, array<string, string>> The legend table
     */
    private function createLegendTable(NGRXMLMetadataExtractor $extractor, DOMElement $attribute): array
    {
        $values = $extractor->query($this->xpaths['schema']['attribute_legend'][0], $attribute);

        return array_map(function (DOMElement $value) use ($extractor): array {
            return [
                'code'       => $extractor->schemaField('attribute_legend_code', false, $value),
                'definition' => $extractor->schemaField('attribute_legend_definition', false, $value),
            ];
        }, iterator_to_array($values));
    }

    /**
     * Attempts to repair a given datetime.
     *
     * - If no time is found, `T00:00:00` is appended
     * - If microseconds are specified, they are removed
     *
     * @param string $datetime The datetime to repair
     *
     * @return string The original, possibly modified, datetime
     */
    private function repairDateTime(string $datetime): string
    {
        $datetime = str_replace(' ', '-', explode(';', $datetime)[0]);

        if (4 === mb_strlen($datetime)) {
            $datetime = $datetime . '-01-01';
        }

        if (7 === mb_strlen($datetime)) {
            $datetime = $datetime . '-01';
        }

        if (mb_strlen($datetime) > 0 && mb_strlen($datetime) < self::DATE_LENGTH_MATCH) {
            // TODO:
            // Dirty fix for the rare occasion that a NGR dataset contains invalid datetime
            // literals. Sometimes a NGR datetime day component is simply a '0'. Append a '1' so
            // that the PHP datetime validation doesn't trip up on this edge-case.
            //
            // This should be detected and properly validated in DCATDateTime in the
            // wterberg/dcat-ap-donl dependency.
            $parts = explode('-', $datetime);

            if (count($parts) >= 3 && '0' === $parts[2]) {
                $parts[2] = $parts[2] . '1';
            }

            $datetime = implode('-', $parts) . $this->time_appendage;
        }

        if (mb_strlen($datetime) > self::DATE_LENGTH_MATCH) {
            $datetime = mb_substr($datetime, 0, self::DATE_LENGTH_MATCH);
        }

        return $datetime;
    }

    /**
     * Attempts to repair a given datetime.
     *
     * - `/` and `,` are replaced with `' '`
     * - Special characters are replaced with `-`
     *
     * @param string $keyword The keyword to repair
     *
     * @return string The original, possibly modified, keyword
     */
    private function repairKeyword(string $keyword): string
    {
        $keyword = preg_replace('/\//', ' ', $keyword);
        $keyword = preg_replace('/,/', ' ', $keyword);
        $keyword = preg_replace(
            $this->tag_config['regex_pattern'],
            $this->tag_config['regex_replacement'],
            $keyword
        );

        return $keyword;
    }
}
