<?php

namespace DonlSync\Catalog;

use DonlSync\ApplicationInterface;
use DonlSync\Configuration;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;

/**
 * Interface ICatalog.
 *
 * Represents a open data catalog to which datasets are send, or from which datasets are harvested.
 */
interface ICatalog
{
    /**
     * ICatalog constructor.
     *
     * @param Configuration        $config      The settings used to properly configure the catalog
     *                                          implementation
     * @param ApplicationInterface $application The current application context
     *
     * @throws CatalogInitializationException Thrown if the given $catalog_settings array does not
     *                                        contain all the data required by the implementation of
     *                                        this interface
     */
    public function __construct(Configuration $config, ApplicationInterface $application);

    /**
     * Returns a sluggified name of the catalog implementation.
     *
     * @return string The name of the catalog
     */
    public function getCatalogSlugName(): string;

    /**
     * Returns the endpoint from which the catalog is available.
     *
     * @return string The url of the catalog
     */
    public function getCatalogEndpoint(): string;

    /**
     * Harvests the catalog and retrieves all the datasets found during this harvesting process.
     *
     * @throws CatalogHarvestingException Thrown if for any reason the harvesting process is
     *                                    interrupted
     *
     * @return array<mixed, mixed> The harvested data from the catalog
     */
    public function getData(): array;
}
