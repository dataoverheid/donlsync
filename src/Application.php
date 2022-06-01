<?php

namespace DonlSync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DonlSync\Catalog\Source\Eindhoven\EindhovenSourceCatalog;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Source\NGR\NGRSourceCatalog;
use DonlSync\Catalog\Source\ODataCatalog\ODataCatalog;
use DonlSync\Catalog\Source\RDW\RDWSourceCatalog;
use DonlSync\Catalog\Source\StelselCatalogus\StelselCatalogusCatalog;
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
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Application.
 *
 * Basic implementation of the ApplicationInterface.
 */
class Application implements ApplicationInterface
{
    /**
     * The current instance of the ApplicationInterface.
     */
    private static ApplicationInterface $instance;

    /**
     * The implementation for reading application input.
     */
    private InputInterface $input;

    /**
     * The implementation for writing application output.
     */
    private OutputInterface $output;

    /**
     * Helper implementation for writing specific messages as output.
     */
    private OutputHelper $output_helper;

    /**
     * The in-memory cache of the loaded configuration files.
     *
     * @var Configuration[]
     */
    private array $configurations;

    /**
     * The active database connection. The connection will be initiated on the first request to
     * Application::database_connection().
     */
    private ?Connection $connection;

    /**
     * The target catalog to which datasets are sent. Will be initiated on the first request to
     * Application::target_catalog().
     */
    private ?ITargetCatalog $target_catalog;

    /**
     * The source catalogs initiated during the execution of the application thus far. A source
     * catalog will be initiated on the first request for the catalog via the
     * Application::source_catalog() method.
     *
     * They keys of this array match the source catalog names.
     *
     * @var array<string, ISourceCatalog>
     */
    private array $source_catalogs;

    /**
     * The implementation to load the external mapping lists. Will be initiated on the first request
     * to Application::mapping_loader().
     */
    private ?MappingLoader $mapping_loader;

    /**
     * The implementation for sending the email summaries to selected recipients. Will be initiated
     * on the first request to Application::smtp_client().
     */
    private ?PHPMailer $mail_client;

    /**
     * The Blade engine used to render the email summaries. Will be initiated on the first request
     * to Application::blade_engine().
     */
    private ?Blade $blade_engine;

    /**
     * The current version of the application. Will be initiated on the first request to
     * Application::version().
     */
    private ?string $version;

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
        $this->version         = null;

        if (empty(self::$instance)) {
            self::$instance = $this;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getInstance(): ApplicationInterface
    {
        if (empty(self::$instance)) {
            throw new RuntimeException(
                'Application singleton request before application was started.'
            );
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function input(): InputInterface
    {
        return $this->input;
    }

    /**
     * {@inheritdoc}
     */
    public function output(): OutputInterface
    {
        return $this->output;
    }

    /**
     * {@inheritdoc}
     */
    public function output_helper(): OutputHelper
    {
        return $this->output_helper;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function source_catalog(string $name): ISourceCatalog
    {
        if (!array_key_exists($name, $this->source_catalogs)) {
            $catalogs = [
                'CBS'       => ODataCatalog::class,
                'CBSDerden' => ODataCatalog::class,
                'Eindhoven' => EindhovenSourceCatalog::class,
                'NGR'       => NGRSourceCatalog::class,
                'RDW'       => RDWSourceCatalog::class,
                'SC'        => StelselCatalogusCatalog::class,
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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

            if ('pdo_mysql' === $_ENV['DB_DRIVER'] && 'false' !== $_ENV['DB_SSL']) {
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function version(): string
    {
        if (null === $this->version) {
            $version  = file_get_contents(self::APP_ROOT . 'VERSION');
            $git_hash = file_exists('/.dockerenv')
                ? file_get_contents(self::APP_ROOT . 'CHECKSUM')
                : trim(exec('git rev-parse --short HEAD'));

            $this->version = sprintf('%s (%s)', $version, $git_hash);
        }

        return $this->version;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function timer(): DateTimer
    {
        try {
            return new DateTimer($this->config('dates')->all());
        } catch (ConfigurationException $e) {
            throw new DonlSyncRuntimeException(
                'Missing or corrupt configuration for creating DateTimer object', 0, $e
            );
        }
    }
}
