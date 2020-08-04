START TRANSACTION;

ALTER TABLE ProcessedDataset
    DROP COLUMN `absent_from_source`;

ALTER TABLE ExecutionMessage
    DROP COLUMN `environment`;

ALTER TABLE ExecutionMessage
    ADD INDEX (`message_processed`);

COMMIT;
