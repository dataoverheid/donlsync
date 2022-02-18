<?php

namespace DonlSync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Dataset\Mapping\MappingLoader;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Helper\DateTimer;
use DonlSync\Helper\OutputHelper;
use GuzzleHttp\Client;
use Jenssegers\Blade\Blade;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface ApplicationInterface.
 *
 * Primary container for all application logic. Responsible for directing and processing all in-
 * and output of the DonlSync application.
 */
interface ApplicationInterface
{
    /**
     * The filesystem path of the application.
     *
     * @var string
     */
    public const APP_ROOT = __DIR__ . '/../';

    /**
     * The filesystem path to the cache directory.
     *
     * @var string
     */
    public const CACHE_DIR = self::APP_ROOT . 'cache/';

    /**
     * The filesystem path to the caching directory for Blade views.
     *
     * @var string
     */
    public const VIEW_CACHE_DIR = self::CACHE_DIR . 'views/';

    /**
     * The filesystem path to the configuration directory.
     *
     * @var string
     */
    public const CONFIG_PATH = self::APP_ROOT . 'config/';

    /**
     * The filesystem path to the logging directory.
     *
     * @var string
     */
    public const LOG_DIR = self::APP_ROOT . 'log/';

    /**
     * The filesystem path to the resources directory.
     *
     * @var string
     */
    public const RESOURCES_DIR = self::APP_ROOT . 'resources/';

    /**
     * The filesystem path to the Blade views directory.
     *
     * @var string
     */
    public const VIEWS_DIR = self::RESOURCES_DIR . 'views/';

    /**
     * Retrieve the current instance of the ApplicationInterface.
     *
     * @return ApplicationInterface The current instance
     */
    public static function getInstance(): ApplicationInterface;

    /**
     * Retrieve the input interface of this application.
     *
     * @return InputInterface The input of the application
     */
    public function input(): InputInterface;

    /**
     * Retrieve the output interface of this application.
     *
     * @return OutputInterface The output of the application
     */
    public function output(): OutputInterface;

    /**
     * Retrieve the output helper.
     *
     * @return OutputHelper The output helper
     */
    public function output_helper(): OutputHelper;

    /**
     * Loads the configuration with the given name. Configuration files are stored in `./config/`
     * as JSON files.
     *
     * @param string $name The requested configuration
     *
     * @throws ConfigurationException If the configuration could not be retrieved for any reason
     *
     * @return Configuration The configuration matching the given name
     */
    public function config(string $name): Configuration;

    /**
     * Instantiates a ITargetCatalog implementation.
     *
     * @throws CatalogInitializationException On any error when initializing the target catalog
     * @throws ConfigurationException         On any configuration related error
     *
     * @return ITargetCatalog The created target catalog
     */
    public function target_catalog(): ITargetCatalog;

    /**
     * Instantiates a ISourceCatalog implementation that matches the given name. Subsequent requests
     * for the same catalog will return the same catalog, it will only be instantiated on the first
     * request (assuming that request was successful).
     *
     * @param string $name The name of the catalog
     *
     * @throws CatalogInitializationException On any error when initializing the source catalog
     * @throws ConfigurationException         On any configuration related error
     *
     * @return ISourceCatalog The created source catalog
     */
    public function source_catalog(string $name): ISourceCatalog;

    /**
     * Creates a GuzzleHttp\Client.
     *
     * @param string $base_uri The optional base uri to assign
     *
     * @throws ConfigurationException On any configuration error
     *
     * @return Client The created client
     */
    public function guzzle_client(string $base_uri = ''): Client;

    /**
     * Retrieves the CKAN credentials for a given catalog from the `.env`.
     *
     * @param string $catalog The catalog to request the credentials for
     *
     * @return string[] The credentials
     */
    public function ckan_credentials(string $catalog): array;

    /**
     * Retrieve the mapping loader to be used by source catalogs.
     *
     * @return MappingLoader The mapping loader
     */
    public function mapping_loader(): MappingLoader;

    /**
     * Returns the active database connection, if none exists, an active connection will be made.
     * Uses the following `.env. variables.
     *
     * - `DB_DRIVER`
     * - `DB_HOST`
     * - `DB_PORT`
     * - `DB_DATABASE`
     * - `DB_USERNAME`
     * - `DB_PASSWORD`
     * - `DB_SSL`
     * - `DB_SSL_CERT`
     * - `DB_SSL_KEY`
     * - `DB_SSL_CA`
     * - `DB_SSL_VERIFY`
     *
     * @throws DBALException On any error while connecting to the database
     *
     * @return Connection The active database connection
     */
    public function database_connection(): Connection;

    /**
     * Retrieves (and instantiates) a PHP mail client to use.
     *
     * @return PHPMailer The PHP mail client
     */
    public function smtp_client(): PHPMailer;

    /**
     * Determines and returns the version of this application. The version is returned in the
     * following format.
     *
     * ```
     * {version number} ({git commit hash})
     * ```
     *
     * @return string The version of the application
     */
    public function version(): string;

    /**
     * Returns the Blade engine used to render templates.
     *
     * @return Blade The blade engine to use
     */
    public function blade_engine(): Blade;

    /**
     * Generates a DateTimer object.
     *
     * @return DateTimer The generated object
     */
    public function timer(): DateTimer;
}
