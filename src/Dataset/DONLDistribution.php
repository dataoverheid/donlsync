<?php

namespace DonlSync\Dataset;

use DCAT_AP_DONL\DCATDistribution;

/**
 * Class DONLDistribution.
 *
 * Extends the standard DCATDistribution so that it is possible to assign a id to a distribution.
 */
class DONLDistribution extends DCATDistribution
{
    /**
     * The CKAN resource ID of the distribution. May be null if the distribution does not exist yet
     * in CKAN.
     */
    protected ?string $id;

    /**
     * DONLDistribution constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->id = null;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed> A key => value array of the entity
     */
    public function getData(): array
    {
        $data = parent::getData();

        if ($this->id) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    /**
     * Getter for the id property.
     *
     * @return string|null The id property
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Assign an id to the distribution.
     *
     * @param string $id The value to set
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
