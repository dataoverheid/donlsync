<?php

namespace DonlSync\Dataset;

use DCAT_AP_DONL\DCATDataset;

/**
 * Class DatasetContainer.
 *
 * Acts as a wrapper of a DCAT_AP_DONL/DCATDataset.
 */
class DatasetContainer
{
    /** @var string|null */
    private $catalog_name;

    /** @var string|null */
    private $catalog_identifier;

    /** @var string|null */
    private $target_identifier;

    /** @var DCATDataset|null */
    private $dataset;

    /** @var string|null */
    private $dataset_hash;

    /** @var int|null */
    private $assigned_number;

    /** @var string[] */
    private $conversion_notices;

    /**
     * DatasetContainer constructor.
     */
    public function __construct()
    {
        $this->catalog_name       = null;
        $this->catalog_identifier = null;
        $this->target_identifier  = null;
        $this->dataset            = null;
        $this->dataset_hash       = null;
        $this->assigned_number    = null;
        $this->conversion_notices = [];
    }

    /**
     * Getter for the catalog_name property, may return null.
     *
     * @return string|null The catalog_name property
     */
    public function getCatalogName(): ?string
    {
        return $this->catalog_name;
    }

    /**
     * Getter for the catalog_identifier property, may return null.
     *
     * @return string|null The catalog_identifier property
     */
    public function getCatalogIdentifier(): ?string
    {
        return $this->catalog_identifier;
    }

    /**
     * Getter for the target_identifier property, may return null.
     *
     * @return string|null The target_identifier property
     */
    public function getTargetIdentifier(): ?string
    {
        return $this->target_identifier;
    }

    /**
     * Getter for the dataset property, may return null.
     *
     * @return DCATDataset|null The dataset property
     */
    public function getDataset(): ?DCATDataset
    {
        return $this->dataset;
    }

    /**
     * Getter for the dataset_hash property, may return null.
     *
     * @return string|null The dataset_hash property
     */
    public function getDatasetHash(): ?string
    {
        return $this->dataset_hash;
    }

    /**
     * Getter for the assigned_number property, may return null.
     *
     * @return int|null The assigned_number property
     */
    public function getAssignedNumber(): ?int
    {
        return $this->assigned_number;
    }

    /**
     * Getter for the conversion_notices property.
     *
     * @return string[] The generated conversion notices
     */
    public function getConversionNotices(): array
    {
        return $this->conversion_notices;
    }

    /**
     * Assigns a catalog_name to this DatasetContainer.
     *
     * @param string|null $catalog_name The value to set
     */
    public function setCatalogName(?string $catalog_name): void
    {
        $this->catalog_name = $catalog_name;
    }

    /**
     * Assigns a catalog_identifier to this DatasetContainer.
     *
     * @param string|null $catalog_identifier The value to set
     */
    public function setCatalogIdentifier(?string $catalog_identifier): void
    {
        $this->catalog_identifier = $catalog_identifier;
    }

    /**
     * Assigns a target_identifier to this DatasetContainer.
     *
     * @param string|null $target_identifier The value to set
     */
    public function setTargetIdentifier(?string $target_identifier): void
    {
        $this->target_identifier = $target_identifier;
    }

    /**
     * Assigns a dataset to this DatasetContainer.
     *
     * @param DCATDataset|null $dataset The value to set
     */
    public function setDataset(?DCATDataset $dataset): void
    {
        $this->dataset = $dataset;
    }

    /**
     * Assigns a dataset_hash to this DatasetContainer.
     *
     * @param string|null $dataset_hash The value to set
     */
    public function setDatasetHash(?string $dataset_hash): void
    {
        $this->dataset_hash = $dataset_hash;
    }

    /**
     * Assigns a assigned_number to this DatasetContainer.
     *
     * @param int|null $assigned_number The value to set
     */
    public function setAssignedNumber(?int $assigned_number): void
    {
        $this->assigned_number = $assigned_number;
    }

    /**
     * Generates a hash based on the current state of the dataset.
     */
    public function generateHash(): void
    {
        $this->dataset_hash = md5(serialize($this->dataset->getData()));
    }

    /**
     * Assigns the conversion_notices generated during the conversion of data into a dataset.
     *
     * @param string[] $conversion_notices The value to set
     */
    public function setConversionNotices(array $conversion_notices): void
    {
        $this->conversion_notices = $conversion_notices;
    }
}
