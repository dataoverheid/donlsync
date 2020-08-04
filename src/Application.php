<?php

namespace DonlSync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NGR\NGRSourceCatalog;
use DonlSync\Catalog\Source\NMGN\NijmegenSourceCatalog;
use DonlSync\Catalog\Source\ODataCatalog\ODataCatalog;
use DonlSync\Catalog\Source\RDW\RDWSourceCatalog;
use DonlSync\Catalog\Target\DONL\DONLTargetCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Dataset\Mapping\MappingLoader;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\DateTimer;
use DonlSync\Helper\OutputHelper;
use GuzzleHttp\Client;
use Jenssegers\Blade\Blade;
use PDO;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Application.
 *
 * Primary container for all application logic. Responsible for directing and processing all in-
 * and output of the DonlSync application.
 */
class Application
{
    /** @var string */
    public const APP_ROOT = __DIR__ . '/../';

    /** @var string */
    public const CACHE_DIR = self::APP_ROOT . 'cache/';

    /** @var string */
    public const VIEW_CACHE_DIR = self::CACHE_DIR . 'views/';

    /** @var string */
    public const CONFIG_PATH = self::APP_ROOT . 'config/';

    /** @var string */
    public const LOG_DIR = self::APP_ROOT . 'log/';

    /** @var string */
    public const RESOURCES_DIR = self::APP_ROOT . 'resources/';

    /** @var string */
    public const VIEWS_DIR = self::RESOURCES_DIR . 'views/';

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    /** @var OutputHelper */
    private $output_helper;

    /** @var Configuration[] */
    private $configurations;

    /** @var Connection */
    private $connection;

    /** @var ITargetCatalog */
    private $target_catalog;

    /** @var ISourceCatalog[] */
    private $source_catalogs;

    /** @var MappingLoader */
    private $mapping_loader;

    /** @var PHPMailer */
    private $mail_client;

    /** @var Blade */
    private $blade_engine;

    /**
     * Application constructor.
     *
     * @param InputInterface  $input  The input of the application
     * @param OutputInterface $output The output of the application
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input           = $input;
        $this->output          = $output;
        $this->output_helper   = new OutputHelper($output);
        $this->configurations  = [];
        $this->connection      = null;
        $this->target_catalog  = null;
        $this->source_catalogs = [];
        $this->mapping_loader  = null;
        $this->mail_client     = null;
        $this->blade_engine    = null;
    }

    /**
     * Retrieve the input interface of this application.
     *
     * @return InputInterface The input of the application
     */
    public function input(): InputInterface
    {
        return $this->input;
    }

    /**
     * Retrieve the output interface of this application.
     *
     * @return OutputInterface The output of the application
     */
    public function output(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Retrieve the output helper.
     *
     * @return OutputHelper The output helper
     */
    public function output_helper(): OutputHelper
    {
        return $this->output_helper;
    }

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
    public function config(string $name): Configuration
    {
        if (!array_key_exists($name, $this->configurations)) {
            $this->configurations[$name] = Configuration::createFromJSONFile(
                self::CONFIG_PATH . $name . '.json'
            );
        }

        return $this->configurations[$name];
    }

    /**
     * Instantiates a ITargetCatalog implementation.
     *
     * @throws CatalogInitializationException On any error when initializing the target catalog
     * @throws ConfigurationException         On any configuration related error
     *
     * @return ITargetCatalog The created target catalog
     */
    public function target_catalog(): ITargetCatalog
    {
        if (empty($this->target_catalog)) {
            $target_config = $this->config('catalog_DONL');
            $target_config->add('catalog_endpoint', $_ENV['CATALOG_TARGET_ENDPOINT']);
            $target_config->add('api_base_path', $_ENV['CATALOG_TARGET_API_BASE']);

            $this->target_catalog = new DONLTargetCatalog($target_config, $this);
        }

        return $this->target_catalog;
    }

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
    public function source_catalog(string $name): ISourceCatalog
    {
        if (!array_key_exists($name, $this->source_catalogs)) {
            $catalogs = [
                'CBS'       => ODataCatalog::class,
                'CBSDerden' => ODataCatalog::class,
                'NGR'       => NGRSourceCatalog::class,
                'NMGN'      => NijmegenSourceCatalog::class,
                'RDW'       => RDWSourceCatalog::class,
            ];

            if (!array_key_exists($name, $catalogs)) {
                throw new CatalogInitializationException('no catalog with name ' . $name);
            }

            $this->source_catalogs[$name] = new $catalogs[$name](
                $this->config('catalog_' . $name), $this
            );
        }

        return $this->source_catalogs[$name];
    }

    /**
     * Creates a GuzzleHttp\Client.
     *
     * @param string $base_uri The optional base uri to assign
     *
     * @throws ConfigurationException On any configuration error
     *
     * @return Client The created client
     */
    public function guzzle_client(string $base_uri = ''): Client
    {
        $http_config = $this->config('http');
        $client_data = [
            'timeout'         => $http_config->get('read_timeout'),
            'connect_timeout' => $http_config->get('connect_timeout'),
            'request_timeout' => $http_config->get('request_timeout'),
            'headers'         => $http_config->get('request_headers'),
        ];

        if (!empty($base_uri)) {
            $client_data['base_uri'] = $base_uri;
        }

        return new Client($client_data);
    }

    /**
     * Retrieves the CKAN credentials for a given catalog from the `.env`.
     *
     * @param string $catalog The catalog to request the credentials for
     *
     * @return string[] The credentials
     */
    public function ckan_credentials(string $catalog): array
    {
        $env_key = 'CATALOG_' . mb_strtoupper($catalog) . '_';

        return [
            'owner_org' => $_ENV[$env_key . 'OWNER_ORG'],
            'user_id'   => $_ENV[$env_key . 'USER_ID'],
            'api_key'   => $_ENV[$env_key . 'API_KEY'],
        ];
    }

    /**
     * Retrieve the mapping loader to be used by source catalogs.
     *
     * @return MappingLoader The mapping loader
     */
    public function mapping_loader(): MappingLoader
    {
        try {
            if (null === $this->mapping_loader) {
                $this->mapping_loader = new MappingLoader(
                    $this->config('dcat')->get('license')['fallback']
                );
            }

            return $this->mapping_loader;
        } catch (ConfigurationException $e) {
            throw new DonlSyncRuntimeException($e->getMessage());
        }
    }

    /**
     * Returns the active database connection, if none exists, an active connection will be made.
     * Uses the following `.env. variables:.
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
    public function database_connection(): Connection
    {
        if (empty($this->connection)) {
            $database_config = [
                'driver'   => $_ENV['DB_DRIVER'],
                'host'     => $_ENV['DB_HOST'],
                'port'     => $_ENV['DB_PORT'],
                'dbname'   => $_ENV['DB_DATABASE'],
                'user'     => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD'],
            ];

            if ('false' !== $_ENV['DB_SSL']) {
                $database_config['driverOptions'] = [
                    PDO::MYSQL_ATTR_SSL_CERT               => $_ENV['DB_SSL_CERT'],
                    PDO::MYSQL_ATTR_SSL_KEY                => $_ENV['DB_SSL_KEY'],
                    PDO::MYSQL_ATTR_SSL_CA                 => $_ENV['DB_SSL_CA'],
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $_ENV['DB_SSL_VERIFY'],
                ];
            }

            $this->connection = DriverManager::getConnection($database_config);
        }

        return $this->connection;
    }

    /**
     * Retrieves (and instantiates) a PHP mail client to use.
     *
     * @return PHPMailer The PHP mail client
     */
    public function smtp_client(): PHPMailer
    {
        if (null === $this->mail_client) {
            try {
                $mail_client             = new PHPMailer(true);
                $mail_client->Host       = $_ENV['SMTP_HOST'];
                $mail_client->Port       = $_ENV['SMTP_PORT'];
                $mail_client->SMTPAuth   = 'false' !== $_ENV['SMTP_AUTH'];
                $mail_client->SMTPSecure = $_ENV['SMTP_SECURE'];
                $mail_client->Username   = $_ENV['SMTP_USERNAME'];
                $mail_client->Password   = $_ENV['SMTP_PASSWORD'];

                $mail_client->isSMTP();
                $mail_client->isHTML(true);
                $mail_client->setFrom($_ENV['SMTP_SOURCE_EMAIL'], $_ENV['SMTP_SOURCE_NAME']);

                $this->mail_client = $mail_client;
            } catch (Exception $e) {
                throw new DonlSyncRuntimeException($e->getMessage());
            }
        }

        return $this->mail_client;
    }

    /**
     * Determines and returns the version of this application. The version is returned in the
     * following format:.
     *
     * ```
     * {version number} ({git commit hash})
     * ```
     *
     * @return string The version of the application
     */
    public function version(): string
    {
        $version  = file_get_contents(self::APP_ROOT . 'VERSION');
        $git_hash = trim(exec('git rev-parse --short HEAD'));

        return sprintf('%s (%s)', $version, $git_hash);
    }

    /**
     * Returns the Blade engine used to render templates.
     *
     * @return Blade The blade engine to use
     */
    public function blade_engine(): Blade
    {
        if (null === $this->blade_engine) {
            $this->blade_engine = new Blade(
                self::VIEWS_DIR, self::VIEW_CACHE_DIR
            );
        }

        return $this->blade_engine;
    }

    /**
     * Generates a DateTimer object.
     *
     * @return DateTimer The generated object
     */
    public function timer(): DateTimer
    {
        try {
            return new DateTimer($this->config('dates')->all());
        } catch (ConfigurationException $e) {
            throw new DonlSyncRuntimeException($e->getMessage());
        }
    }
}
