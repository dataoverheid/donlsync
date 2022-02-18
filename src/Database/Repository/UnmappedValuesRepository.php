<?php

namespace DonlSync\Database\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Table;
use DonlSync\Application;

/**
 * Class UnmappedValuesRepository.
 *
 * Allows for interaction with the UnmappedValues database table.
 */
class UnmappedValuesRepository
{
    /**
     * The name of the table in the database.
     *
     * @var string
     */
    public const TABLE_NAME = 'UnmappedValues';

    /**
     * Creates the database table corresponding to this repository.
     *
     * @param Connection $connection The current database connection
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function createTable(Connection $connection): void
    {
        $schema = $connection->createSchemaManager();

        if ($schema->tablesExist(self::TABLE_NAME)) {
            return;
        }

        $unmapped_values = new Table(self::TABLE_NAME);

        $unmapped_values->addColumn('id', 'integer', [
            'unsigned'      => true,
            'autoincrement' => true,
        ]);
        $unmapped_values->addColumn('object', 'text');
        $unmapped_values->addColumn('attribute', 'text');
        $unmapped_values->addColumn('value', 'text');

        $unmapped_values->setPrimaryKey(['id']);

        $schema->createTable($unmapped_values);
    }

    /**
     * Deletes all records from the database table.
     *
     * @param Connection $connection The current database connection
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function clearTable(Connection $connection): void
    {
        $qb = $connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->executeStatement();
    }

    /**
     * Inserts a given record into the database.
     *
     * The given record must contain the following keys:
     * - object
     * - attribute
     * - value
     *
     * @param array<string, mixed> $record The record to create
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function insertRecord(array $record): void
    {
        $connection = Application::getInstance()->database_connection();

        $qb      = $connection->createQueryBuilder();
        $records = $qb->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('object', ':object'))
            ->andWhere($qb->expr()->eq('attribute', ':attribute'))
            ->andWhere($qb->expr()->eq('value', ':value'))
            ->setParameter('object', $record['object'])
            ->setParameter('attribute', $record['attribute'])
            ->setParameter('value', $record['value'])
            ->executeQuery();

        if ($records->rowCount() > 0) {
            return;
        }

        $connection->createQueryBuilder()
            ->insert(self::TABLE_NAME)
            ->setValue('object', ':object')
            ->setValue('attribute', ':attribute')
            ->setValue('value', ':value')
            ->setParameter('object', $record['object'])
            ->setParameter('attribute', $record['attribute'])
            ->setParameter('value', $record['value'])
            ->executeStatement();
    }

    /**
     * Write all the records of this database table to the given file.
     *
     * @param string $filePath The path to store the records
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function recordsToFile(string $filePath): void
    {
        $connection = Application::getInstance()->database_connection();

        $qb      = $connection->createQueryBuilder();
        $records = $qb->select('object', 'attribute', 'value')
            ->from(self::TABLE_NAME)
            ->orderBy('object', 'ASC')
            ->addOrderBy('attribute', 'ASC')
            ->fetchAllAssociative();

        $output = '';

        foreach ($records as $record) {
            $output .= sprintf("%s, %s, %s\n",
                $record['object'],
                $record['attribute'],
                $record['value']
            );
        }

        file_put_contents($filePath, $output);
    }
}
