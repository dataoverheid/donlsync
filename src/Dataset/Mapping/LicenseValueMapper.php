<?php

namespace DonlSync\Dataset\Mapping;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATException;

/**
 * Class LicenseValueMapper.
 *
 * Ensures that a mapping applied to a license falls back to a default license if no mapping exists.
 */
class LicenseValueMapper extends ValueMapper
{
    /** @var string */
    private const DCAT_VALUELIST = 'DONL:License';

    /** @var string */
    private $fallback_license;

    /**
     * {@inheritdoc}
     *
     * @param string $fallback_license The fallback license to use
     */
    public function __construct(array $map = [], string $fallback_license = '')
    {
        parent::__construct($map);

        $this->fallback_license = $fallback_license;
    }

    /**
     * {@inheritdoc}
     *
     * If no mapping is found for the given value and the value itself is not considered a valid
     * DCAT-AP-DONL license, then a value is returned as defined by `dcat.json`.
     */
    public function map(string $value): string
    {
        $mapped_value = parent::map($value);
        $dcat_license = new DCATControlledVocabularyEntry(
            $mapped_value, self::DCAT_VALUELIST
        );

        try {
            if ($mapped_value === $value && !$dcat_license->validate()->validated()) {
                $mapped_value = $this->fallback_license;
            }
        } catch (DCATException $e) {
        }

        return $mapped_value;
    }
}
