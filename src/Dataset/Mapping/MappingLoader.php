<?php

namespace DonlSync\Dataset\Mapping;

use DonlSync\Exception\MappingException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class MappingLoader.
 *
 * Enables the loading of external mapping resources into AbstractMapper implementations.
 */
class MappingLoader
{
    /**
     * The fields for which to use the special LicenseValueMapper rather than the standard
     * ValueMapper.
     *
     * @var string[]
     */
    private array $license_fields;

    /**
     * The fully namespaced path of the class to use for value-mapping license fields.
     */
    private string $license_value_mapper;

    /**
     * The license to use as a fallback if no valid license can be constructed.
     */
    private string $fallback_license;

    /**
     * MappingLoader constructor.
     *
     * @param string $fallback_license The fallback license to use for the LicenseValueMapper
     */
    public function __construct(string $fallback_license)
    {
        $this->license_fields       = ['license', 'resource.license'];
        $this->license_value_mapper = LicenseValueMapper::class;
        $this->fallback_license     = $fallback_license;
    }

    /**
     * Loads the default mappings from an online source and fills a DefaultMapper instance with the
     * extracted mappings.
     *
     * The online source must be a JSON file adhering to the following structure:
     * `{dcat field}`: `{default value}`. Distribution defaults must prefix their `{dcat field}`
     * values with `'resource.'`.
     *
     * @param string $source The URL from which to load the mapping
     * @param Client $client The HTTP client to use
     *
     * @throws MappingException If, for any reason, the mappings could not be retrieved from the
     *                          given url
     *
     * @return DefaultMapper The mapper with the loaded mappings included
     */
    public function loadDefaultMappings(string $source, Client $client): DefaultMapper
    {
        $mapper = new DefaultMapper();
        $mapper->setMap($this->loadJSONContentsFromURL($source, $client));

        return $mapper;
    }

    /**
     * Loads the mappings found at the given URL and assigns them to the given AbstractMapper
     * implementation.
     *
     * The `$sources` array must adhere to the following structure:
     *
     * ```
     * [
     *   {
     *     "attribute": "{DCAT property}"
     *     "url": "{online resource}"
     *     "field": "{field containing the target value}"
     *   }
     * ]
     * ```
     *
     * @param array<string, array> $sources       The URLs from which to load the mappings
     * @param string               $mapping_class The mapping class to instantiate and return
     * @param Client               $client        The HTTP client to use
     *
     * @throws MappingException If, for any reason, the mappings could not be retrieved from a given
     *                          url
     *
     * @return ValueMapper[]|BlacklistMapper[]|WhitelistMapper[] The mappers as a
     *                                                           {name} => {mapper instance} array
     */
    public function loadMappingFromURL(array $sources, string $mapping_class, Client $client): array
    {
        $mappers = [];

        foreach ($sources as $source) {
            $mapper        = $this->createMapper($source['attribute'], $mapping_class);
            $json_contents = $this->loadJSONContentsFromURL($source['url'], $client);
            $map           = [];

            foreach ($json_contents as $row) {
                $map[$row['name']] = $row[$source['field']];
            }

            $mapper->setMap($map);

            $mappers[$source['attribute']] = $mapper;
        }

        return $mappers;
    }

    /**
     * Creates the appropriate mapper based on the attribute the mapper is for and the requested
     * mapping class.
     *
     * Specifically a `LicenseValueMapper` is returned rather than the requested `ValueMapper` if
     * the mapper is intended for one of the license fields.
     *
     * @param string $attribute       The attribute to create a mapper for
     * @param string $requested_class The fully qualified name of the mapping implementation
     *
     * @return ValueMapper|LicenseValueMapper|BlacklistMapper|WhitelistMapper The created mapper
     */
    private function createMapper(string $attribute, string $requested_class)
    {
        if (ValueMapper::class !== $requested_class) {
            return new $requested_class();
        }

        if (in_array($attribute, $this->license_fields)) {
            return new $this->license_value_mapper([], $this->fallback_license);
        }

        return new $requested_class();
    }

    /**
     * Retrieves the JSON contents located at a given url.
     *
     * @param string $url    The URL holding the JSON contents
     * @param Client $client The HTTP client to use
     *
     * @throws MappingException If, for any reason, the mappings could not be retrieved from the
     *                          given url
     *
     * @return array<mixed, mixed> The JSON contents
     */
    private function loadJSONContentsFromURL(string $url, Client $client): array
    {
        try {
            $response = $client->get($url);

            if (200 != $response->getStatusCode()) {
                throw new MappingException(sprintf(
                    'mapping url %s responded with HTTP status code %s',
                    $url, $response->getStatusCode()
                ));
            }

            $response_body = $response->getBody()->getContents();

            if ('' === $response_body) {
                throw new MappingException(
                    sprintf('the mapping at %s has not been properly set up', $url)
                );
            }

            $json_contents = json_decode($response_body, true);

            if (null === $json_contents) {
                throw new MappingException(
                    sprintf('the mapping at %s contains invalid JSON', $url)
                );
            }

            return $json_contents;
        } catch (RequestException $e) {
            throw new MappingException(
                'failed to load mapping from external url', 0, $e
            );
        }
    }
}
