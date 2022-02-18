<?php

namespace DonlSync\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Database\Repository\ProcessedDatasetsRepository;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\DatabaseAnalyzerException;
use DonlSync\Helper\OutputHelper;

/**
 * Class DatabaseAnalyzer.
 *
 * Ensures that the database records accurately represents the contents of the target catalog. This
 * ensures that the synchronization process will correctly synchronize the datasets which require
 * synchronization.
 */
class DatabaseAnalyzer
{
    /**
     * The current database connection.
     */
    protected ?Connection $connection;

    /**
     * The target catalog to which datasets are sent by the application.
     */
    protected ?ITargetCatalog $target_catalog;

    /**
     * The source catalog being processed by the application.
     */
    protected ?ISourceCatalog $source_catalog;

    /**
     * Helper implementation for writing specific messages as output.
     */
    protected ?OutputHelper $output_helper;

    /**
     * Whether to write any output.
     */
    protected bool $should_output;

    /**
     * DatabaseAnalyzer constructor.
     */
    public function __construct()
    {
        $this->connection     = null;
        $this->target_catalog = null;
        $this->source_catalog = null;
        $this->output_helper  = null;
        $this->should_output  = true;
    }

    /**
     * Getter for the connection property.
     *
     * @return Connection|null The connection property
     */
    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Getter for the target_catalog property.
     *
     * @return ITargetCatalog|null The target_catalog property
     */
    public function getTargetCatalog(): ?ITargetCatalog
    {
        return $this->target_catalog;
    }

    /**
     * Getter for the source_catalog property.
     *
     * @return ISourceCatalog|null The source_catalog property
     */
    public function getSourceCatalog(): ?ISourceCatalog
    {
        return $this->source_catalog;
    }

    /**
     * Getter for the output_helper property.
     *
     * @return OutputHelper|null The output_helper property
     */
    public function getOutputHelper(): ?OutputHelper
    {
        return $this->output_helper;
    }

    /**
     * Setter for the connection property.
     *
     * @param Connection $connection The value to set
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Setter for the target_catalog property.
     *
     * @param ITargetCatalog $catalog The value to set
     */
    public function setTargetCatalog(ITargetCatalog $catalog): void
    {
        $this->target_catalog = $catalog;
    }

    /**
     * Setter for the source_catalog property.
     *
     * @param ISourceCatalog $catalog The value to set
     */
    public function setSourceCatalog(ISourceCatalog $catalog): void
    {
        $this->source_catalog = $catalog;
    }

    /**
     * Setter for the output_helper property.
     *
     * @param OutputHelper $output_helper The value to set
     */
    public function setOutputHelper(OutputHelper $output_helper): void
    {
        $this->output_helper = $output_helper;
    }

    /**
     * Disables the output of this DatabaseAnalyzer.
     */
    public function disableOutput(): void
    {
        $this->should_output = false;
    }

    /**
     * Ensures that the records in the local database accurately represent the datasets which are
     * present on the given application represented by the ITargetCatalog.
     *
     * This ensures that the synchronization process correctly synchronizes the datasets which
     * require synchronization.
     *
     * @throws DatabaseAnalyzerException Thrown on any database interaction error
     */
    public function analyze(): void
    {
        try {
            try {
                if ($this->should_output) {
                    $this->output_helper->writeDatabaseAnalyzerIntro(
                        $this->target_catalog, $this->source_catalog
                    );
                }

                $datasets_on_target   = $this->target_catalog->getData(
                    $this->source_catalog->getCredentials()
                );
                $datasets_on_database = ProcessedDatasetsRepository::getRecordsByCatalogName(
                    $this->connection, $this->source_catalog->getCatalogSlugName()
                );

                if ($this->should_output) {
                    $this->output_helper->writeDatabaseAnalyzerRecordsFound(
                        count($datasets_on_target), count($datasets_on_database)
                    );
                }

                $this->deleteAbsentRecords($datasets_on_target, $datasets_on_database);
                $this->createMissingRecords($datasets_on_target, $datasets_on_database);

                if ($this->should_output) {
                    $this->output_helper->writeDatabaseAnalyzerConcluded();
                }
            } catch (CatalogHarvestingException $e) {
                if ($this->should_output) {
                    $this->output_helper->writeDatabaseAnalyzerAborted($e->getMessage());
                }
            }

            if ($this->should_output) {
                $this->output_helper->writeDivider('-');
            }
        } catch (DBALException $e) {
            throw new DatabaseAnalyzerException($e->getMessage());
        }
    }

    /**
     * Deletes all database records which no longer point to an existing dataset on the target
     * catalog.
     *
     * @param array<int, array> $datasets_on_target   The datasets as they exist on the target catalog
     * @param array<int, array> $datasets_on_database The datasets as they exist in the local database
     */
    private function deleteAbsentRecords(array $datasets_on_target,
                                         array $datasets_on_database): void
    {
        $deletion_errors        = [];
        $deletion_actions_taken = false;

        if ($this->should_output) {
            $this->output_helper->writeDatabaseAnalyzerRecordDeletionIntro();
        }

        foreach ($datasets_on_database as $database_dataset) {
            $dataset_exists_on_catalog = false;

            foreach ($datasets_on_target as $target_dataset) {
                if ($database_dataset['target_identifier'] === $target_dataset['id']) {
                    $dataset_exists_on_catalog = true;

                    break;
                }
            }

            if (!$dataset_exists_on_catalog) {
                $deletion_actions_taken = true;

                try {
                    ProcessedDatasetsRepository::deleteRecordByTargetIdentifier(
                        $this->connection, $database_dataset['target_identifier']
                    );

                    if ($this->should_output) {
                        $this->output_helper->writeDatabaseAnalyzerRecordActionTaken(
                            $database_dataset['catalog_identifier']
                        );
                    }
                } catch (DBALException $e) {
                    $deletion_errors[] = $database_dataset['catalog_identifier'];
                }
            }
        }

        if ($this->should_output) {
            $this->output_helper->writeDatabaseAnalyzerDeletionSummary(
                $deletion_errors, $deletion_actions_taken
            );
        }
    }

    /**
     * Creates database records for all datasets that exist on the target catalog but are absent in
     * the local database.
     *
     * @param array<int, array> $datasets_on_target   The datasets as they exist on the target catalog
     * @param array<int, array> $datasets_on_database The datasets as they exist in the local database
     */
    private function createMissingRecords(array $datasets_on_target,
                                          array $datasets_on_database): void
    {
        $creation_errors        = [];
        $creation_actions_taken = false;

        if ($this->should_output) {
            $this->output_helper->writeDatabaseAnalyzerRecordCreationIntro();
        }

        foreach ($datasets_on_target as $target_dataset) {
            $dataset_exists_in_database = false;

            foreach ($datasets_on_database as $database_dataset) {
                if ($target_dataset['id'] === $database_dataset['target_identifier']) {
                    $dataset_exists_in_database = true;

                    break;
                }
            }

            if ($dataset_exists_in_database) {
                continue;
            }

            $creation_actions_taken = true;

            try {
                $record = [
                    'catalog_name'       => $this->source_catalog->getCatalogSlugName(),
                    'catalog_identifier' => $target_dataset['identifier'],
                    'target_identifier'  => $target_dataset['id'],
                    'dataset_hash'       => $target_dataset['donlsync_checksum'] ?? 'unknown',
                    'assigned_number'    => (int) explode('-', $target_dataset['name'])[0],
                ];
                ProcessedDatasetsRepository::insertRecord($this->connection, $record);

                if ($this->should_output) {
                    $this->output_helper->writeDatabaseAnalyzerRecordActionTaken(
                        $target_dataset['identifier']
                    );
                }
            } catch (DBALException $e) {
                $creation_errors[] = $target_dataset['identifier'];
            }
        }

        if ($this->should_output) {
            $this->output_helper->writeDatabaseAnalyzerCreationSummary(
                $creation_errors, $creation_actions_taken
            );
        }
    }
}
