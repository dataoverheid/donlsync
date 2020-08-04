<?php

namespace DonlSync\Dataset\Mapping;

/**
 * Class BlacklistMapper.
 *
 * Allows for checking if values are blacklisted as specified by the Mapper values.
 */
class BlacklistMapper extends AbstractMapper
{
    /**
     * Checks if a given value is blacklisted according to the given blacklist.
     *
     * @param string $value The value to check
     *
     * @return bool Whether or not the given value is blacklisted
     */
    public function isBlacklisted(string $value): bool
    {
        return in_array($value, $this->map);
    }
}
