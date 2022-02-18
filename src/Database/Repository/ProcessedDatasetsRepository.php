<?php

namespace DonlSync\Database\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Table;
use DonlSync\Dataset\DatasetContainer;

/**
 * Class ProcessedDatasetsRepository.
 *
 * Allows for interaction with the ProcessedDataset database table.
 */
class ProcessedDatasetsRepository
{
    /**
     * The name of the table in the database.
     *
     * @var string
     */
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
        $schema = $connection->getSchemaManager();

        if ($schema->tablesExist(self::TABLE_NAME)) {
            return;
        }

        $processed_dataset = new Table(self::TABLE_NAME);

        $processed_dataset->addColumn('catalog_name', 'string');
        $processed_dataset->addColumn('catalog_identifier', 'string');
        $processed_dataset->addColumn('target_identifier', 'string', [
            'notnull' => false,
        ]);
        $processed_dataset->addColumn('dataset_hash', 'string', [
            'notnull' => false,
        ]);
        $processed_dataset->addColumn('assigned_number', 'integer', [
            'unsigned'      => true,
            'autoincrement' => true,
        ]);

        $processed_dataset->setPrimaryKey(['catalog_name', 'catalog_identifier']);
        $processed_dataset->addUniqueIndex(['target_identifier']);
        $processed_dataset->addIndex(['assigned_number']);

        $schema->createTable($processed_dataset);
    }

    /**
     * Retrieves all records with the given catalog_name property.
     *
     * @param Connection $connection   The current database connection
     * @param string     $catalog_name The name of the catalog to filter on
     *
     * @throws DBALException Thrown on any database interaction error
     *
     * @return array<int, array> The records matching the query
     */
    public static function getRecordsByCatalogName(Connection $connection,
                                                   string $catalog_name): array
    {
        $qb = $connection->createQueryBuilder();

        return $qb->select(
                'catalog_name',
                'catalog_identifier',
                'target_identifier',
                'dataset_hash',
                'assigned_number'
            )
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('catalog_name', ':catalog_name'))
            ->setParameter('catalog_name', $catalog_name)
            ->execute()
            ->fetchAllAssociative();
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
     * @param Connection           $connection The current database connection
     * @param array<string, mixed> $record     The record to create
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function insertRecord(Connection $connection, array $record): void
    {
        $connection->createQueryBuilder()
            ->insert(self::TABLE_NAME)
            ->setValue('catalog_name', ':catalog_name')
            ->setValue('catalog_identifier', ':catalog_identifier')
            ->setValue('target_identifier', ':target_identifier')
            ->setValue('dataset_hash', ':dataset_hash')
            ->setValue('assigned_number', ':assigned_number')
            ->setParameter('catalog_name', $record['catalog_name'])
            ->setParameter('catalog_identifier', $record['catalog_identifier'])
            ->setParameter('target_identifier', $record['target_identifier'])
            ->setParameter('dataset_hash', $record['dataset_hash'])
            ->setParameter('assigned_number', $record['assigned_number'])
            ->execute();
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
        $connection->createQueryBuilder()
            ->delete(self::TABLE_NAME)
            ->where('target_identifier = :target_identifier')
            ->setParameter('target_identifier', $target_identifier)
            ->execute();
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
        $qb = $connection->createQueryBuilder();
        $qb->update(self::TABLE_NAME)
            ->set('dataset_hash', ':dataset_hash')
            ->where($qb->expr()->eq('target_identifier', ':target_identifier'))
            ->setParameter('dataset_hash', $record->getDatasetHash())
            ->setParameter('target_identifier', $record->getTargetIdentifier())
            ->execute();
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
        $connection->createQueryBuilder()
            ->insert(self::TABLE_NAME)
            ->setValue('catalog_name', ':catalog_name')
            ->setValue('catalog_identifier', ':catalog_identifier')
            ->setParameter('catalog_name', $dataset->getCatalogName())
            ->setParameter('catalog_identifier', $dataset->getCatalogIdentifier())
            ->execute();
    }

    /**
     * Selects ands returns the assigned_number value of the record which is identifier by the given
     * catalog_name and catalog_identifier.
     *
     * @param Connection $connection The current database connection
     * @param string     $name       The catalog_name of the record
     * @param string     $identifier The catalog_identifier of the record
     *
     * @throws DBALException Thrown on any database interaction error
     *
     * @return int The assigned number for the requested record
     */
    public static function getAssignedNumberByCatalogNameAndIdentifier(Connection $connection,
                                                                       string $name,
                                                                       string $identifier): int
    {
        $qb      = $connection->createQueryBuilder();
        $results = $qb->select('assigned_number')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('catalog_name', ':catalog_name'))
            ->andWhere($qb->expr()->eq('catalog_identifier', ':catalog_identifier'))
            ->setParameter('catalog_name', $name)
            ->setParameter('catalog_identifier', $identifier)
            ->execute()
            ->fetchAllAssociative();

        if (0 === count($results)) {
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
        $qb = $connection->createQueryBuilder();
        $qb->update(self::TABLE_NAME)
            ->set('target_identifier', ':target_identifier')
            ->set('dataset_hash', ':dataset_hash')
            ->where($qb->expr()->eq('catalog_name', ':catalog_name'))
            ->andWhere($qb->expr()->eq('catalog_identifier', ':catalog_identifier'))
            ->andWhere($qb->expr()->eq('assigned_number', ':assigned_number'))
            ->setParameter('target_identifier', $dataset->getTargetIdentifier())
            ->setParameter('dataset_hash', $dataset->getDatasetHash())
            ->setParameter('catalog_name', $dataset->getCatalogName())
            ->setParameter('catalog_identifier', $dataset->getCatalogIdentifier())
            ->setParameter('assigned_number', $dataset->getAssignedNumber())
            ->execute();
    }
}
