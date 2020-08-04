<?php

namespace DonlSync\Database;

use Doctrine\DBAL\Connection;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Helper\OutputHelper;

/**
 * Class DatabaseAnalyzerBuilder.
 *
 * Responsible for preparing and building DatabaseAnalyzer objects.
 */
class DatabaseAnalyzerBuilder
{
    /** @var DatabaseAnalyzer */
    protected $build;

    /**
     * DatabaseAnalyzerBuilder constructor.
     */
    public function __construct()
    {
        $this->build = new DatabaseAnalyzer();
    }

    /**
     * Returns the build DatabaseAnalyzer.
     *
     * @return DatabaseAnalyzer The build object
     */
    public function build(): DatabaseAnalyzer
    {
        return $this->build;
    }

    /**
     * Adds a database connection to the DatabaseAnalyzer.
     *
     * @param Connection $connection The connection to use
     *
     * @return DatabaseAnalyzerBuilder This, for method chaining
     */
    public function withDatabaseConnection(Connection $connection): DatabaseAnalyzerBuilder
    {
        $this->build->setConnection($connection);

        return $this;
    }

    /**
     * Adds a ITargetCatalog to the DatabaseAnalyzer.
     *
     * @param ITargetCatalog $target The target to use
     *
     * @return DatabaseAnalyzerBuilder This, for method chaining
     */
    public function withTargetCatalog(ITargetCatalog $target): DatabaseAnalyzerBuilder
    {
        $this->build->setTargetCatalog($target);

        return $this;
    }

    /**
     * Adds the ISourceCatalog to the DatabaseAnalyzer.
     *
     * @param ISourceCatalog $source_catalog The source catalog to use
     *
     * @return DatabaseAnalyzerBuilder This, for method chaining
     */
    public function withSourceCatalog(ISourceCatalog $source_catalog): DatabaseAnalyzerBuilder
    {
        $this->build->setSourceCatalog($source_catalog);

        return $this;
    }

    /**
     * Adds a OutputHelper to the DatabaseAnalyzer.
     *
     * @param OutputHelper $helper The helper to use
     *
     * @return DatabaseAnalyzerBuilder This, for method chaining
     */
    public function withOutputHelper(OutputHelper $helper): DatabaseAnalyzerBuilder
    {
        $this->build->setOutputHelper($helper);

        return $this;
    }

    /**
     * Disables the output of the DatabaseAnalyzer.
     *
     * @return DatabaseAnalyzerBuilder This, for method chaining
     */
    public function withoutOutput(): DatabaseAnalyzerBuilder
    {
        $this->build->disableOutput();

        return $this;
    }
}
