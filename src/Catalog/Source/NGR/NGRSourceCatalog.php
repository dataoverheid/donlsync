<?php

namespace DonlSync\Catalog\Source\NGR;

use DonlSync\Application;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NGR\BuildRule\NGRBuildRuleFactory;
use DonlSync\Catalog\Source\NGR\Tools\NGRXMLMetadataExtractor;
use DonlSync\Configuration;
use DonlSync\Dataset\Builder\BuilderConfiguration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class NGRSourceCatalog.
 *
 * Represents the NGR open data catalog.
 */
class NGRSourceCatalog implements ISourceCatalog
{
    /** @var int */
    private const DATE_LENGTH_MATCH = 19;

    /** @var string */
    private $catalog_name;

    /** @var string */
    private $catalog_endpoint;

    /** @var string[] */
    private $credentials;

    /** @var Client */
    private $api_client;

    /** @var BuilderConfiguration */
    private $builder_config;

    /** @var array */
    private $api_config;

    /** @var array */
    private $tag_config;

    /** @var array */
    private $xpaths;

    /** @var string */
    private $http_default;

    /** @var Configuration */
    private $dcat_config;

    /** @var string */
    private $time_appendage;

    /** @var string */
    private $id_pattern;

    /**
     * {@inheritdoc}
     *
     * The following array keys are expected in `$catalog_settings`:
     * - catalog_name
     * - catalog_endpoint
     * - api_base_path
     * - mappings
     * - api_id_request_size
     * - api_dataset_request_size
     */
    public function __construct(Configuration $config, Application $application)
    {
        try {
            $this->catalog_name     = $config->get('catalog_name');
            $this->catalog_endpoint = $config->get('catalog_endpoint');
            $this->credentials      = $application->ckan_credentials($this->catalog_name);
            $this->api_client       = $application->guzzle_client($config->get('api_base_path'));
            $this->api_config       = $config->get('api');
            $this->xpaths           = $config->get('xpath_selectors');
            $this->tag_config       = $application->config('ckan')->get('tags');
            $this->http_default     = $application->config('http')->get('procotol');
            $this->dcat_config      = $application->config('dcat');
            $this->time_appendage   = $this->dcat_config->get('datetime_appendage');
            $this->id_pattern       = $config->get('identifier_pattern');

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

        for ($i = 1; $i < $dataset_count_on_ngr; $i = $i + $id_rows) {
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
     * @param array $ids The ids of the datasets to harvest
     *
     * @throws CatalogHarvestingException On any interaction error while communicating with the API
     *
     * @return array The harvested datasets
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
     * @return array The extracted metadata
     */
    private function extractData(string $xml, int $index): array
    {
        $extractor  = new NGRXMLMetadataExtractor($xml, $this->xpaths);
        $harvest    = [
            'language'           => $extractor->datasetField('language', $index, true),
            'license'            => $extractor->getDatasetLicense($index),
            'theme'              => $extractor->datasetField('theme', $index, true),
            'contact_point_name' => $extractor->getContactPointName($index),
            'modificationDate'   => $extractor->getModificationDate($index),
            'conformsTo'         => $extractor->datasetField('conformsTo', $index, true),
        ];

        $fields = [
            'identifier', 'landingPage', 'title', 'description', 'metadataLanguage', 'authority',
            'publisher', 'contact_point_email', 'contact_point_phone', 'contact_point_webpage',
            'releaseDate',
        ];

        foreach ($fields as $field) {
            $harvest[$field] = $extractor->datasetField($field, $index);
        }

        $harvest['identifier']            = sprintf($this->id_pattern, $harvest['identifier']);
        $harvest['landingPage']           = $this->repairURL($harvest['landingPage']);
        $harvest['contact_point_webpage'] = $this->repairURL($harvest['contact_point_webpage']);
        $harvest['modificationDate']      = $this->repairDateTime($harvest['modificationDate']);
        $harvest['releaseDate']           = $this->repairDateTime($harvest['releaseDate']);

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

            $resource['accessURL'] = $this->repairURL($resource['accessURL']);

            if ('' === $resource['title']) {
                $resource['title'] = $resource['format'];
            }

            $harvest['resources'][] = $resource;
        }

        return $harvest;
    }

    /**
     * Attempts to repair a given URL.
     *
     * - Replaces `http:\\` with `http://`
     * - Replaces `https:\\` with `https://`
     * - If the URL starts with `www`, it prepends `https://`.
     * - Spaces are replaced with `%20`
     *
     * @param string $url The url to repair
     *
     * @return string The original, possibly modified, url
     */
    private function repairURL(string $url): string
    {
        $url = preg_replace('/http:\\\\\\\\/', $this->http_default, $url);
        $url = preg_replace('/https:\\\\\\\\/', $this->http_default, $url);

        if ('www' === mb_substr($url, 0, 3)) {
            $url = $this->http_default . $url;
        }

        $url = str_replace(' ', '%20', $url);

        return $url;
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
        $datetime = str_replace(' ', '-', $datetime);

        if (mb_strlen($datetime) > 0 && mb_strlen($datetime) < self::DATE_LENGTH_MATCH) {
            $datetime = $datetime . $this->time_appendage;
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
