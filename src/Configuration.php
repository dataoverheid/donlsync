<?php

namespace DonlSync;

use DonlSync\Exception\ConfigurationException;

/**
 * Class Configuration.
 *
 * Represents `{key} => {value}` pair configuration settings.
 */
class Configuration
{
    /** @var array */
    private $data;

    /**
     * Configuration constructor.
     *
     * @param array $data The configuration data
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
     * @throws ConfigurationException If the given configuration file does not exist
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

        if (!file_exists($path)) {
            throw new ConfigurationException(
                'Configuration file at [ ' . $path . ' ] does not appear to exist'
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
     * Retrieve a specific key from the configuration data.
     *
     * @param string $key The key holding the requested setting
     *
     * @throws ConfigurationException If no such key is present in the data
     *
     * @return string|int|array The value behind the key
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
     * @return array The configuration data
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
