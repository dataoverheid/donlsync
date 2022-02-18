<?php

namespace DonlSync\Catalog\Target;

use DonlSync\Catalog\ICatalog;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogPublicationException;

/**
 * Interface ITargetCatalog.
 *
 * Represents a catalog web-application to which datasets are sent.
 */
interface ITargetCatalog extends ICatalog
{
    /** @var string[] */
    public const CREDENTIAL_KEYS = [
        'owner_org',
        'user_id',
        'api_key',
    ];

    /**
     * Harvests the catalog and retrieves all the datasets found during this harvesting process.
     * When the `$credentials` argument is provided the following keys **must** be present:.
     *
     * - `owner_org`
     * - `user_id`
     * - `api_key`
     *
     * @param string[] $credentials The authentication credentials to use
     *
     * @throws CatalogHarvestingException Thrown if for any reason the harvesting process is
     *                                    interrupted
     *
     * @return array<mixed, mixed> The harvested data from the catalog
     */
    public function getData(array $credentials = []): array;

    /**
     * Retrieves the properties which should persist regardless of the DONLSync mutation operations
     * on a dataset.
     *
     * @return array<string, array> The properties of a datasets which should persists on the target catalog
     */
    public function getPersistentProperties(): array;

    /**
     * Publishes a given dataset on the target catalog. The `$credentials` argument requires the
     * following keys to be present:.
     *
     * - `owner_org`
     * - `user_id`
     * - `api_key`
     *
     * @param DatasetContainer $container   The object which contains the dataset to be published
     * @param string[]         $credentials The authentication credentials to use
     *
     * @throws CatalogPublicationException Thrown if for any reason the target catalog rejects the
     *                                     dataset
     *
     * @return string The id of the published dataset
     */
    public function publishDataset(DatasetContainer $container, array $credentials): string;

    /**
     * Updates a given dataset on the target catalog.
     *
     * @param DatasetContainer $container   The object which contains the dataset to be updated
     * @param string[]         $credentials The authentication credentials to use
     *
     * @throws CatalogPublicationException Thrown if for any reason the target catalog rejects the
     *                                     dataset
     */
    public function updateDataset(DatasetContainer $container, array $credentials): void;

    /**
     * Permanently deletes a given dataset from the target catalog.
     *
     * @param string   $id          The id of the dataset on the target catalog to be deleted
     * @param string[] $credentials The authentication credentials to use
     *
     * @throws CatalogPublicationException Thrown if for any reason the target catalog rejects the
     *                                     deletion request
     */
    public function deleteDataset(string $id, array $credentials): void;

    /**
     * Retrieves a dataset with the matching identifier on the target catalog.
     *
     * @param string $id The id of the dataset on the target catalog
     *
     * @throws CatalogPublicationException Thrown if for any reason the target catalog fails to
     *                                     return the requested dataset
     *
     * @return array<string, mixed> The requested dataset, including its distributions
     */
    public function getDataset(string $id): array;
}
