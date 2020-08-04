<?php

namespace DonlSync\Database\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

/**
 * Class ExecutionMessageRepository.
 *
 * Allows for interaction with the ExecutionMessage database table.
 */
class ExecutionMessageRepository
{
    /** @var string */
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
        $connection->executeQuery('
            CREATE TABLE IF NOT EXISTS ExecutionMessage (
                `id`                INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `message_date`      DATETIME         NOT NULL DEFAULT NOW(),
                `message`           TEXT             NOT NULL,
                `message_processed` BIT(1)           NOT NULL DEFAULT b\'0\',
            
                PRIMARY KEY (`id`),
                INDEX (`message_date`),
                INDEX (`message_processed`)
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
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
     * @return array The records matching the query
     */
    public static function getMessagesByDate(Connection $connection, string $date,
                                             bool $mark_as_processed = true): array
    {
        $statement = $connection->prepare('
            SELECT  id,
                    DATE(message_date) AS message_date,
                    message
            FROM    ExecutionMessage
            WHERE   DATE(message_date) = ?
              AND   message_processed = b\'0\'
        ');
        $statement->execute([$date]);

        $results = $statement->fetchAll();

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
     * @param Connection $connection The current database connection
     * @param array      $record     The record to create
     *
     * @throws DBALException Thrown on any database interaction error
     */
    public static function insertRecord(Connection $connection, array $record): void
    {
        $statement = $connection->prepare('
            INSERT INTO ExecutionMessage
                (message)
                VALUES(?)
        ');
        $statement->execute([
            $record['message'],
        ]);
        $statement->closeCursor();
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
        $statement = $connection->prepare('
            UPDATE ExecutionMessage
                SET     message_processed = b\'1\'
                WHERE   id = ?
        ');
        $statement->execute([$id]);
        $statement->closeCursor();
    }
}
