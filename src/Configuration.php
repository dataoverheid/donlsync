<?php

namespace DonlSync;

use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\DonlSyncRuntimeException;

/**
 * Class Configuration.
 *
 * Represents `{key} => {value}` pair configuration settings.
 */
class Configuration
{
    /**
     * The configuration data as a `{key} => {value}` array.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * Configuration constructor.
     *
     * @param array<string, mixed> $data The configuration data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Creates a configuration instance based on a given JSON file.
     *
     * @param string $path The path to the JSON file relative to the root of this project
     *
     * @throws ConfigurationException If the given configuration file cannot be accessed
     * @throws ConfigurationException If the given configuration file is not a JSON file
     * @throws ConfigurationException If the given configuration file contains invalid JSON
     *
     * @return Configuration The Configuration object filled with the JSON contents of the given
     *                       file
     */
    public static function createFromJSONFile(string $path): Configuration
    {
        if (!is_readable($path)) {
            throw new ConfigurationException(
                'Cannot access configuration file at [ ' . $path . ' ]'
            );
        }

        $file_contents = file_get_contents($path);
        $json_data     = json_decode($file_contents, true);

        if (!is_array($json_data)) {
            throw new ConfigurationException(
                'Configuration file at [ ' . $path . ' ] contains invalid JSON'
            );
        }

        return new Configuration($json_data);
    }

    /**
     * Checks if the given `$data` array contains the required keys. If any key is absent a
     * `DonlSyncRuntimeException` will be thrown.
     *
     * @param array<string, mixed> $data The data that should contain the given keys
     * @param string[]             $keys The keys that must be present in the data
     */
    public static function checkKeys(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new DonlSyncRuntimeException('Missing configuration key: ' . $key);
            }
        }
    }

    /**
     * Retrieve a specific key from the configuration data.
     *
     * @param string $key The key holding the requested setting
     *
     * @throws ConfigurationException If no such key is present in the data
     *
     * @return string|int|bool|array<mixed, mixed> The value behind the key
     */
    public function get(string $key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        throw new ConfigurationException('No configuration present with key ' . $key);
    }

    /**
     * Retrieve all the defined configuration data.
     *
     * @return array<string, mixed> The configuration data
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Adds a given configuration entry to the list overwriting any previous value held by the key.
     *
     * @param string $key   The key under which the data will be set
     * @param mixed  $value The value to set under the key
     */
    public function add(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}
