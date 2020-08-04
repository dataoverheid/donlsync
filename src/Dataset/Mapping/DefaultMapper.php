<?php

namespace DonlSync\Dataset\Mapping;

/**
 * Class DefaultMapper.
 *
 * Enables the use of default values for specific fields.
 */
class DefaultMapper extends AbstractMapper
{
    /**
     * Checks if a default value is present for a given field.
     *
     * @param string $field The field to check for
     *
     * @return bool Whether or not a default value is present
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->map);
    }

    /**
     * Retrieve the default value to be used for the given field.
     *
     * @param string $field The field to retrieve the default for
     *
     * @return string|null The default value, or null if no default is set
     */
    public function getDefault(string $field): ?string
    {
        if ($this->has($field)) {
            return $this->map[$field];
        }

        return null;
    }
}
