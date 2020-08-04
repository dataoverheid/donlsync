<?php

namespace DonlSync\Dataset\Mapping;

/**
 * Class WhitelistMapper.
 *
 * Allows for checking if values are whitelisted as specified by the Mapper values.
 */
class WhitelistMapper extends AbstractMapper
{
    /**
     * Checks if a given value is whitelisted according to the given whitelist.
     *
     * @param string $value The value to check
     *
     * @return bool Whether or not the given value is whitelisted
     */
    public function inWhitelist(string $value): bool
    {
        return in_array($value, $this->map);
    }
}
