<?php

namespace DonlSync\Database\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Table;
use PDO;

/**
 * Class ExecutionMessageRepository.
 *
 * Allows for interaction with the ExecutionMessage database table.
 */
class ExecutionMessageRepository
{
    /**
     * The name of the table in the database.
     *
     * @var string
     */
    public const TABLE_NAME = 'ExecutionMessage';

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

        $execution_message = new Table(self::TABLE_NAME);

        $execution_message->addColumn('id', 'integer', [
            'unsigned'      => true,
            'autoincrement' => true,
        ]);
        $execution_message->addColumn('message_date', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        $execution_message->addColumn('message', 'text');
        $execution_message->addColumn('message_processed', 'boolean', [
            'default' => false,
        ]);

        $execution_message->setPrimaryKey(['id']);
        $execution_message->addIndex(['message_date']);
        $execution_message->addIndex(['message_processed']);

        $schema->createTable($execution_message);
    }

    /**
     * Retrieves all records with the given catalog_name property.
     *
     * @param Connection $connection        The current database connection
     * @param string     $date              The date of execution
     * @param bool       $mark_as_processed Whether or not to mark the messages as processed
     *                                      afterwards
     *
     * @throws DBALException Thrown on any database interaction error
     *
     * @return array<int, array> The records matching the query
     */
    public static function getMessagesByDate(Connection $connection, string $date,
                                             bool $mark_as_processed = true): array
    {
        $qb      = $connection->createQueryBuilder();
        $results = $qb->select('id', 'message')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->gte('message_date', ':message_date_start'))
            ->andWhere($qb->expr()->lte('message_date', ':message_date_end'))
            ->andWhere($qb->expr()->eq('message_processed', ':is_processed'))
            ->setParameter('message_date_start', $date . ' 00:00:00')
            ->setParameter('message_date_end', $date . ' 23:59:59')
            ->setParameter('is_processed', false, PDO::PARAM_BOOL)
            ->execute()
            ->fetchAllAssociative();

        if ($mark_as_processed) {
            foreach ($results as $result) {
                self::markRecordAsProcessed($connection, $result['id']);
            }
        }

        return $results;
    }

    /**
     * Inserts a given record into the database.
     *
     * The given record must contain the following keys:
     * - message
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
            ->setValue('message', ':message')
            ->setParameter('message', $record['message'])
            ->execute();
    }

    /**
     * Marks a given record as processed.
     *
     * @param Connection $connection The current database connection
     * @param int        $id         The id of the record
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function markRecordAsProcessed(Connection $connection, int $id): void
    {
        $qb = $connection->createQueryBuilder();
        $qb->update(self::TABLE_NAME)
            ->set('message_processed', ':message_processed')
            ->where($qb->expr()->eq('id', ':id'))
            ->setParameter('message_processed', true, PDO::PARAM_BOOL)
            ->setParameter('id', $id)
            ->execute();
    }
}
