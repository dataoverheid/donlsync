<?php

namespace DonlSync\Helper;

use DonlSync\Exception\DonlSyncRuntimeException;

/**
 * Class Summarizer.
 *
 * Holds the summary of the import results
 */
class Summarizer
{
    /** @var string[] */
    public const SUMMARY_KEYS = [
        'validated_datasets',
        'created_datasets',
        'updated_datasets',
        'ignored_datasets',
        'rejected_datasets',
        'discarded_datasets',
        'deleted_datasets',
        'conflict_datasets',
    ];

    /** @var array */
    private $data;

    /** @var array */
    private $current_data;

    /**
     * Summarizer constructor.
     *
     * @param array $data The base summary to start with
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;

        foreach (self::SUMMARY_KEYS as $key) {
            $this->current_data[$key] = 0;
        }
    }

    /**
     * Create a Summarizer instance from a JSON file.
     *
     * @param string $file The JSON file to create the summarizer from
     *
     * @return Summarizer The created summarizer
     */
    public static function fromFile(string $file): Summarizer
    {
        return new Summarizer(json_decode(file_get_contents($file), true));
    }

    /**
     * Increments a counter of the summary.
     *
     * @param string $key The key to increment
     */
    public function incrementKey(string $key): void
    {
        if (!array_key_exists($key, $this->current_data)) {
            throw new DonlSyncRuntimeException('unknown summary key ' . $key);
        }

        ++$this->current_data[$key];
    }

    /**
     * Retrieve the data behind a specific key of the summary.
     *
     * @param string $key The key to retrieve
     *
     * @return mixed The value behind the key
     */
    public function get(string $key)
    {
        if (!array_key_exists($key, $this->current_data)) {
            throw new DonlSyncRuntimeException('unknown summary key ' . $key);
        }

        return $this->current_data[$key];
    }

    /**
     * Writes the JSON encoded summary to the given file.
     *
     * @param string $file    The file to write to
     * @param string $catalog The catalog that was summarized
     *
     * @return bool Whether or not writing to file succeeded
     */
    public function writeToFile(string $file, string $catalog): bool
    {
        $this->data[$catalog] = [];

        foreach (self::SUMMARY_KEYS as $key) {
            $this->data[$catalog][$key] = $this->current_data[$key];
        }

        return false !== file_put_contents($file, json_encode($this->data));
    }
}
