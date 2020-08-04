<?php

namespace DonlSync\Dataset\Mapping;

/**
 * Class ValueMapper.
 *
 * Allows for transforming of values from a > b as specified by the given Mappings.
 */
class ValueMapper extends AbstractMapper
{
    /**
     * Transforms the original value into its mapped value as defined by the mappingURL. If no
     * mapping exists for the given value, the original value will be returned.
     *
     * @param string $value The original, unmapped, value
     *
     * @return string The transformed value
     */
    public function map(string $value): string
    {
        foreach ($this->map as $from => $to) {
            if (mb_strtolower($from) === mb_strtolower($value)) {
                return $to;
            }
        }

        return $value;
    }
}
