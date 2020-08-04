<?php

namespace DonlSync\Database\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use DonlSync\Dataset\DatasetContainer;

/**
 * Class ProcessedDatasetsRepository.
 *
 * Allows for interaction with the ProcessedDataset database table.
 */
class ProcessedDatasetsRepository
{
    /** @var string */
    public const TABLE_NAME = 'ProcessedDataset';

    /**
     * Creates the database table corresponding to this repository.
     *
     * @param Connection $connection The current database connection
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function createTable(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE IF NOT EXISTS ProcessedDataset (
                `catalog_name`       VARCHAR(100)     NOT NULL,
                `catalog_identifier` VARCHAR(255)     NOT NULL,
                `target_identifier`  VARCHAR(255)     DEFAULT NULL,
                `dataset_hash`       CHAR(32)         DEFAULT NULL,
                `assigned_number`    INT(10) UNSIGNED NOT NULL      AUTO_INCREMENT,
            
                PRIMARY KEY (`catalog_name`, `catalog_identifier`),
                UNIQUE INDEX (`target_identifier`),
                INDEX (`assigned_number`)
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    /**
     * Retrieves all records with the given catalog_name property.
     *
     * @param Connection $connection   The current database connection
     * @param string     $catalog_name The name of the catalog to filter on
     *
     * @throws DBALException Thrown on any database interaction error
     *
     * @return array The records matching the query
     */
    public static function getRecordsByCatalogName(Connection $connection,
                                                   string $catalog_name): array
    {
        $statement = $connection->prepare('
            SELECT  catalog_name, 
                    catalog_identifier, 
                    target_identifier, 
                    dataset_hash, 
                    assigned_number
            FROM    ProcessedDataset
            WHERE   catalog_name = ?
        ');
        $statement->execute([$catalog_name]);

        return $statement->fetchAll();
    }

    /**
     * Inserts a given record into the database.
     *
     * The given record must contain the following keys:
     * - catalog_name
     * - catalog_identifier
     * - target_identifier
     * - dataset_hash
     * - assigned_number
     *
     * @param Connection $connection The current database connection
     * @param array      $record     The record to create
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function insertRecord(Connection $connection, array $record): void
    {
        $statement = $connection->prepare('
            INSERT INTO ProcessedDataset
                (catalog_name, catalog_identifier, target_identifier, dataset_hash, assigned_number)
                VALUES(?, ?, ?, ?, ?)
        ');
        $statement->execute([
            $record['catalog_name'],
            $record['catalog_identifier'],
            $record['target_identifier'],
            $record['dataset_hash'],
            $record['assigned_number'],
        ]);
        $statement->closeCursor();
    }

    /**
     * Removes a record from the database with the matching target_identifier.
     *
     * @param Connection $connection        The current database connection
     * @param string     $target_identifier The value to identify the record by
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function deleteRecordByTargetIdentifier(Connection $connection,
                                                          string $target_identifier): void
    {
        $statement = $connection->prepare('
            DELETE FROM ProcessedDataset
                WHERE   target_identifier = ?;
        ');
        $statement->execute([$target_identifier]);
        $statement->closeCursor();
    }

    /**
     * Updates the given dataset record in the database.
     *
     * @param Connection       $connection The current database connection
     * @param DatasetContainer $record     The dataset record to update
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function updateRecord(Connection $connection, DatasetContainer $record): void
    {
        $statement = $connection->prepare('
            UPDATE ProcessedDataset
                SET   dataset_hash = ?
                WHERE target_identifier = ?
        ');
        $statement->execute([
            $record->getDatasetHash(),
            $record->getTargetIdentifier(),
        ]);
        $statement->closeCursor();
    }

    /**
     * Creates a record into the database based on only the catalog_name and catalog_identifier.
     *
     * @param Connection       $connection The current database connection
     * @param DatasetContainer $dataset    The dataset to create
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function createMinimalRecord(Connection $connection,
                                               DatasetContainer $dataset): void
    {
        $statement = $connection->prepare('
            INSERT INTO ProcessedDataset
                (catalog_name, catalog_identifier)
                VALUES (?, ?)
        ');
        $statement->execute([
            $dataset->getCatalogName(),
            $dataset->getCatalogIdentifier(),
        ]);
        $statement->closeCursor();
    }

    /**
     * Selects ands returns the assigned_number value of the record which is identifier by the given
     * catalog_name and catalog_identifier.
     *
     * @param Connection $connection The current database connection
     * @param string     $name       The catalog_name of the record
     * @param string     $identifier The catalog_identifier of the record
     *
     *@throws DBALException Thrown on any database interaction error
     *
     * @return int The assigned number for the requested record
     */
    public static function getAssignedNumberByCatalogNameAndIdentifier(Connection $connection,
                                                                       string $name,
                                                                       string $identifier): int
    {
        $statement = $connection->prepare('
            SELECT  assigned_number
            FROM    ProcessedDataset
            WHERE   catalog_name = ?
              AND   catalog_identifier = ?
        ');
        $statement->execute([
            $name,
            $identifier,
        ]);
        $results = $statement->fetchAll();

        if (0 == count($results)) {
            throw new DBALException('No results for query');
        }

        return (int) $results[0]['assigned_number'];
    }

    /**
     * Updates the given dataset in the database by updating its target_identifier and dataset_hash
     * properties.
     *
     * @param Connection       $connection The current database connection
     * @param DatasetContainer $dataset    The dataset to update
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function updateRecordFully(Connection $connection,
                                             DatasetContainer $dataset): void
    {
        $statement = $connection->prepare('
            UPDATE ProcessedDataset
                SET     target_identifier = ?,
                        dataset_hash = ?
                WHERE   catalog_name = ?
                  AND   catalog_identifier = ?
                  AND   assigned_number = ?
        ');
        $statement->execute([
            $dataset->getTargetIdentifier(),
            $dataset->getDatasethash(),
            $dataset->getCatalogName(),
            $dataset->getCatalogIdentifier(),
            $dataset->getAssignedNumber(),
        ]);
        $statement->closeCursor();
    }
}
