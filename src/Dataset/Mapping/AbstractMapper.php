<?php

namespace DonlSync\Dataset\Mapping;

/**
 * Class AbstractMapper.
 *
 * Base implementation of the mapping functionality.
 */
abstract class AbstractMapper
{
    /** @var string[] */
    protected $map;

    /**
     * AbstractMapper constructor.
     *
     * @param string[] $map The mapping data
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    /**
     * Returns the entire mapping dictionary.
     *
     * @return string[] The fully loaded map
     */
    public function getFullMap(): array
    {
        return $this->map;
    }

    /**
     * Uses the given mapping for future mapping actions.
     *
     * @param string[] $map The mappings to use
     */
    public function setMap(array $map): void
    {
        $this->map = $map;
    }
}
