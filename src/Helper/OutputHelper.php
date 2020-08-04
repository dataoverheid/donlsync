<?php

namespace DonlSync\Helper;

use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class OutputHelper.
 *
 * Allows for easy writing to the application output.
 */
class OutputHelper
{
    /** @var OutputInterface */
    protected $output;

    /**
     * OutputHelper constructor.
     *
     * @param OutputInterface $output The output stream of the application
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Writes a tabbed string to the application output with an optional header.
     *
     * @param string $header   The header string to put before the tab
     * @param string $message  The message to write after the tab
     * @param int    $tab_size The width in characters of the first tab
     */
    public function writeTabbed(string $header = '', string $message = '', int $tab_size = 27): void
    {
        if (mb_strlen($header) < $tab_size) {
            $header = $header . str_repeat(' ', $tab_size - mb_strlen($header));
        }

        $this->output->writeln($header . $message);
    }

    /**
     * Writes a dividing line to the output stream.
     *
     * @param string $divider  The character to use for the divider
     * @param int    $width    The width of the line
     * @param bool   $newlines Whether or not to surround the dividing line with newlines
     */
    public function writeDivider(string $divider = '=', int $width = 55,
                                 bool $newlines = true): void
    {
        if ($newlines) {
            $this->output->writeln('');
        }

        $this->output->writeln(str_repeat($divider, $width));

        if ($newlines) {
            $this->output->writeln('');
        }
    }

    public function writeAnalyzerHeader(ITargetCatalog $target): void
    {
        $this->writeDivider();
        $this->output->writeln(sprintf(
            '    DonlSync database analyzer: %s (%s)',
            mb_strtoupper($target->getCatalogSlugName()), $target->getCatalogEndpoint())
        );
        $this->writeDivider();
    }

    /**
     * Writes the synchronization introduction and configuration to the application output stream.
     *
     * @param array     $source_catalog_settings The settings of the source catalog
     * @param array     $target_catalog_settings The settings of the target catalog
     * @param DateTimer $stopwatch               The object tracking the execution time
     */
    public function writeHeader(array $source_catalog_settings, array $target_catalog_settings,
                                DateTimer $stopwatch): void
    {
        $tab_size = 35;

        $this->writeDivider();
        $this->output->writeln(sprintf(
            '    DonlSync imports: %s',
            mb_strtoupper($source_catalog_settings['catalog_name']))
        );
        $this->writeDivider();

        $this->output->writeln(
            'Synchronization started at ' . $stopwatch->getStartTimeFormatted()
        );
        $this->output->writeln('');

        $this->output->writeln('Configuration');
        $this->writeTabbed('  External catalog', sprintf('%s (%s)',
            $source_catalog_settings['catalog_name'], $source_catalog_settings['catalog_endpoint']
        ), $tab_size);
        $this->writeTabbed('  Target catalog', sprintf('%s (%s)',
            $target_catalog_settings['catalog_name'], $_ENV['CATALOG_TARGET_ENDPOINT']
        ), $tab_size);

        $default_message = !array_key_exists('defaults', $source_catalog_settings['mappings'])
            ? 'None'
            : $source_catalog_settings['mappings']['defaults'];

        $this->writeTabbed('  Default mappings', $default_message, $tab_size);

        $this->output->writeln('  Mapping lists');
        if (!array_key_exists('transformations', $source_catalog_settings['mappings'])) {
            $this->output->writeln('    None');
        } else {
            if (0 === count($source_catalog_settings['mappings']['transformations'])) {
                $this->output->writeln('    None');
            }

            foreach ($source_catalog_settings['mappings']['transformations'] as $mapping) {
                $this->writeTabbed(
                    '    ' . $mapping['attribute'], $mapping['url'], $tab_size
                );
            }
        }

        $this->output->writeln('  Blacklist filters');
        if (!array_key_exists('blacklists', $source_catalog_settings['mappings'])) {
            $this->output->writeln('    None');
        } else {
            if (0 === count($source_catalog_settings['mappings']['blacklists'])) {
                $this->output->writeln('    None');
            }

            foreach ($source_catalog_settings['mappings']['blacklists'] as $mapping) {
                $this->writeTabbed(
                    '    ' . $mapping['attribute'], $mapping['url'], $tab_size
                );
            }
        }

        $this->output->writeln('  Whitelist filters');
        if (!array_key_exists('whitelists', $source_catalog_settings['mappings'])) {
            $this->output->writeln('    None');
        } else {
            if (0 === count($source_catalog_settings['mappings']['whitelists'])) {
                $this->output->writeln('    None');
            }

            foreach ($source_catalog_settings['mappings']['whitelists'] as $mapping) {
                $this->writeTabbed(
                    '    ' . $mapping['attribute'], $mapping['url'], $tab_size
                );
            }
        }

        $this->writeDivider();
    }

    /**
     * Writes the synchronization summary to the application output stream.
     *
     * @param Summarizer $summary   The summary to write
     * @param DateTimer  $stopwatch The timer running since the application start
     */
    public function writeSummary(Summarizer $summary, DateTimer $stopwatch): void
    {
        $this->writeDivider();

        $this->output->writeln(
            'Synchronization ended at ' . $stopwatch->getEndTimeFormatted()
        );

        $this->output->writeln('');
        $this->writeTabbed('Runtime', $stopwatch->getDurationFormatted());
        $this->writeTabbed('Memory usage', sprintf(
            '%s MB', memory_get_peak_usage(true) / 1000 / 1000)
        );

        $this->output->writeln('');
        $this->writeTabbed('  Validated datasets', $summary->get('validated_datasets'));
        $this->writeTabbed('    Created datasets', $summary->get('created_datasets'));
        $this->writeTabbed('    Updated datasets', $summary->get('updated_datasets'));
        $this->writeTabbed('    Ignored datasets', $summary->get('ignored_datasets'));
        $this->writeTabbed('    Rejected datasets', $summary->get('rejected_datasets'));

        $this->output->writeln('');
        $this->writeTabbed('  Discarded datasets', $summary->get('discarded_datasets'));

        $this->output->writeln('');
        $this->writeTabbed('  Deleted datasets', $summary->get('deleted_datasets'));

        $this->output->writeln('');
        $this->writeTabbed('  Conflicts', $summary->get('conflict_datasets'));

        $this->writeDivider();
    }

    /**
     * Writes the introduction to the database analyzer section of the execution.
     *
     * @param ITargetCatalog $catalog The target catalog being compared
     * @param ISourceCatalog $source  The source catalog being compared
     */
    public function writeDatabaseAnalyzerIntro(ITargetCatalog $catalog,
                                               ISourceCatalog $source): void
    {
        $this->output->writeln(sprintf(
            'Comparing local database contents to contents on target catalog'
        ));
        $this->output->writeln('');
        $this->output->writeln(sprintf(' > Target catalog: %s (%s)',
            $catalog->getCatalogSlugName(), $catalog->getCatalogEndpoint()
        ));
        $this->output->writeln(sprintf(' > Source catalog: %s (%s)',
            $source->getCatalogSlugName(), $source->getCatalogEndpoint()
        ));
    }

    /**
     * Write the amount of records found in the database and on the target.
     *
     * @param int $dataset_on_target    The amount of datasets found on the target
     * @param int $datasets_on_database The amount of datasets found in the database
     */
    public function writeDatabaseAnalyzerRecordsFound(int $dataset_on_target,
                                                      int $datasets_on_database): void
    {
        $this->output->writeln('');
        $this->writeTabbed('Catalog records', $dataset_on_target, 30);
        $this->writeTabbed('Database records', $datasets_on_database, 30);
        $this->output->writeln('');
    }

    /**
     * Write that DonlSync will now start deleting records without a corresponding dataset.
     */
    public function writeDatabaseAnalyzerRecordDeletionIntro(): void
    {
        $this->output->writeln('');
        $this->output->writeln(
            'Removed records for datasets that no longer exist on the target catalog:'
        );
    }

    /**
     * Write that an action has been taken on the database record with the given catalog_identifier.
     *
     * @param string $identifier The identifier of the dataset
     */
    public function writeDatabaseAnalyzerRecordActionTaken(string $identifier): void
    {
        $this->output->writeln(' - ' . $identifier);
    }

    /**
     * Writes a final summary of the record deletion step.
     *
     * @param string[] $deletion_errors The identifier of datasets which could not be deleted
     * @param bool     $actions_taken   If any attempt has been make to take action
     */
    public function writeDatabaseAnalyzerDeletionSummary(array $deletion_errors,
                                                         bool $actions_taken): void
    {
        if (count($deletion_errors) > 0) {
            foreach ($deletion_errors as $identifier) {
                $this->output->writeln(' - Failed to delete record ' . $identifier);
            }
        }

        if (!$actions_taken) {
            $this->output->writeln(' None');
        }
    }

    /**
     * Write that DonlSync will now start creating records for datasets.
     */
    public function writeDatabaseAnalyzerRecordCreationIntro(): void
    {
        $this->output->writeln('');
        $this->output->writeln(
            'Created records for datasets that are present on the target catalog:'
        );
    }

    /**
     * Writes a final summary of the record creation step.
     *
     * @param string[] $creation_errors The identifier of datasets which could not be deleted
     * @param bool     $actions_taken   If any attempt has been make to take action
     */
    public function writeDatabaseAnalyzerCreationSummary(array $creation_errors,
                                                         bool $actions_taken): void
    {
        if (count($creation_errors) > 0) {
            foreach ($creation_errors as $identifier) {
                $this->output->writeln(' - Failed to create record ' . $identifier);
            }
        }

        if (!$actions_taken) {
            $this->output->writeln(' None');
        }
    }

    /**
     * Write that the database analyzer component has finished.
     */
    public function writeDatabaseAnalyzerConcluded(): void
    {
        $this->output->writeln('');
        $this->output->writeln('Analysis complete.');
    }

    /**
     * write that the database analyzer was aborted as the target catalog could not be reached.
     *
     * @param string $message The error message
     */
    public function writeDatabaseAnalyzerAborted(string $message): void
    {
        $this->output->writeln(
            'Database synchronization aborted, the target catalog could not be reached'
        );
        $this->output->writeln('Error: ' . $message);
    }

    /**
     * Writes about how many potential datasets were found in the source catalog.
     *
     * @param int    $amount   The amount of potential datasets
     * @param string $endpoint The endpoint of the catalog
     */
    public function writeDataFound(int $amount, string $endpoint): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf('Found [%s] objects on %s', $amount, $endpoint));
        $this->output->writeln('');
    }

    /**
     * Writes the introduction of a dataset to process.
     *
     * @param array          $dataset        The dataset to process
     * @param ITargetCatalog $target         The target catalog
     * @param array          $known_datasets The known datasets on the target catalog
     */
    public function writeDatasetIntro(array $dataset, ITargetCatalog $target,
                                      array $known_datasets): void
    {
        $this->writeDivider('-');
        $this->writeTabbed('Dataset', $dataset['title'] ?? 'no title', 11);
        $this->writeTabbed('', $dataset['identifier'] ?? 'no identifier', 11);

        foreach ($known_datasets as $known_dataset) {
            if ($dataset['identifier'] === $known_dataset['catalog_identifier']) {
                $this->writeTabbed('', sprintf('%s/dataset/%s',
                    $target->getCatalogEndpoint(), $known_dataset['target_identifier']
                ), 11);

                break;
            }
        }
    }

    /**
     * Writes that the currently processed dataset has a identifier conflict.
     */
    public function writeDatasetIdentifierConflict(): void
    {
        $this->writeTabbed('State', 'Conflict', 11);
        $this->writeTabbed('Reasons', 'Duplicate identifier encountered', 11);
        $this->writeTabbed('Action', 'Skip', 11);
        $this->writeTabbed('Result', 'Skipped', 11);
        $this->writeTabbed(
            'Reason',
            'The identifier of this dataset matches the identifier of a previously ' .
            'synchronised dataset',
            11
        );
    }

    /**
     * Writes that the current dataset failed its validation.
     *
     * @param string[] $messages The validation messages
     */
    public function writeDatasetInvalid(array $messages): void
    {
        $this->writeTabbed('State', 'Invalid', 11);

        $first_message = true;

        foreach ($messages as $message) {
            if ($first_message) {
                $this->writeTabbed('Reasons', $message, 11);
                $first_message = false;

                continue;
            }

            $this->writeTabbed('', $message, 11);
        }

        $this->writeTabbed('Action', 'Discard', 11);
        $this->writeTabbed('Result', 'Discarded', 11);
        $this->writeTabbed('Reason', 'The dataset failed validation', 11);
    }

    /**
     * Writes the introduction to the dataset deletion component.
     *
     * @param int $datasets_to_delete The amount of datasets to delete
     */
    public function writeDatasetDeletionIntroduction(int $datasets_to_delete): void
    {
        $this->writeDivider();
        $this->output->writeln('Found ' . $datasets_to_delete . ' datasets to delete');
    }

    /**
     * Writes that the amount of datasets to delete exceeds the configured threshold for deleting
     * datasets.
     *
     * @param int   $threshold The amount of datasets corresponding to the configured threshold
     * @param float $limit     The limit to be enforced
     */
    public function writeExceededDatasetDeletionThreshold(int $threshold, float $limit): void
    {
        $this->writeDivider('-');

        $this->writeTabbed('Deletion threshold', sprintf('%s %%', $limit * 100));
        $this->writeTabbed('', $threshold . ' datasets');

        $this->output->writeln(
            'Amount of datasets to delete exceeds configured threshold, disabling ' .
            'dataset deletion'
        );
    }

    /**
     * Writes the introduction of a dataset to be deleted.
     *
     * @param array $dataset The dataset to be deleted
     */
    public function writeDatasetToDelete(array $dataset): void
    {
        $this->writeDivider('-');

        $this->writeTabbed('Dataset', $dataset['title'], 11);
        $this->writeTabbed('', $dataset['identifier'], 11);
        $this->writeTabbed('Action', 'Delete', 11);
    }

    /**
     * Write that the dataset is deleted.
     */
    public function writeDatasetDeleted(): void
    {
        $this->writeTabbed('Result', 'Deleted', 11);
    }

    /**
     * Write that the target catalog rejected the dataset deletion request.
     *
     * @param string $message The returned error message
     */
    public function writeDatasetDeletionRejected(string $message): void
    {
        $this->writeTabbed('Result', 'Error', 11);
        $this->writeTabbed('Reason',
            'The target catalog rejected the deletion request', 11
        );
        $this->writeTabbed('', $message, 11);
    }

    /**
     * Write that the dataset is deleted from the target catalog but that a record still exists of
     * the dataset in the local database.
     *
     * @param string $message The returned error message
     */
    public function writeDatasetRecordDeletionFailure(string $message): void
    {
        $this->writeTabbed('Result', 'Partial success', 11);
        $this->writeTabbed('Reason',
            'The dataset is removed from the catalog but not from the database', 11
        );
        $this->writeTabbed('', $message, 11);
    }

    /**
     * Write that the synchronization of the catalog was cancelled because a unrecoverable error
     * occurred.
     *
     * @param string|null $message The error message
     */
    public function writeDatasetSynchronizationCancelled(?string $message = null)
    {
        $this->output->writeln('Cancelling synchronization, unrecoverable error.');

        if ($message) {
            $this->output->writeln(sprintf('Error: %s', $message));
        }
    }

    /**
     * Write that no datasets are deleted because the target catalog was unable to provide accurate
     * dataset information.
     */
    public function writeDatasetRemovalCancelled(): void
    {
        $this->output->writeln(
            'Failed to get accurate dataset data from target catalog, abandoning cleanup.'
        );
    }

    /**
     * Write that the dataset is ready for synchronization.
     */
    public function writeDatasetSynchronizeAction(): void
    {
        $this->writeTabbed('Action', 'Synchronize', 11);
    }

    /**
     * Write that a dataset is ignored because of a hash match.
     */
    public function writeDatasetIgnored(): void
    {
        $this->writeTabbed('Result', 'Ignored', 11);
        $this->writeTabbed(
            'Reason', 'No changes since last synchronization', 11
        );
    }

    /**
     * Writes that the dataset is not deleted because of the current execution configuration.
     */
    public function writeDatasetNotDeleted(): void
    {
        $this->writeTabbed('Result', 'Ignored', 11);
        $this->writeTabbed(
            'Reason', 'Dataset deletions are disabled in current execution',
            11
        );
    }

    /**
     * Write that persistent properties could not be set because the target catalog was unable to
     * provide the dataset.
     *
     * @param string $message The error message
     */
    public function writeDatasetUpdateComparisonRejected(string $message): void
    {
        $this->writeTabbed('Result', 'Rejected', 11);
        $this->writeTabbed(
            'Reason',
            'Failed to compare dataset to the dataset on the target catalog',
            11
        );
        $this->writeTabbed('', $message, 11);
    }

    /**
     * Write that the update request was rejected by the target catalog for the given dataset.
     *
     * @param string $message The error message
     */
    public function writeDatasetUpdateRejected(string $message): void
    {
        $this->writeTabbed('Result', 'Rejected', 11);
        $this->writeTabbed(
            'Reason', 'Failed to update the dataset on the target catalog', 11
        );
        $this->writeTabbed('Error', $message, 11);
    }

    /**
     * Write that the dataset is updated on the target catalog.
     */
    public function writeDatasetUpdated(): void
    {
        $this->writeTabbed('Result', 'Updated', 11);
    }

    /**
     * Write that the dataset is updated on the target catalog, but not in the local database.
     *
     * @param string $message The error message
     */
    public function writeDatasetUpdatedButNotTheDatabase(string $message): void
    {
        $this->writeTabbed('Result', 'Partial update', 11);
        $this->writeTabbed(
            'Reason', 'The dataset is updated on the target catalog', 11
        );
        $this->writeTabbed(
            '', 'Failed to update the local database with the changes', 11
        );
        $this->writeTabbed('Error', $message, 11);
    }

    /**
     * Write that the dataset is created on the target catalog.
     *
     * @param string $id The id of the dataset
     */
    public function writeDatasetCreated(string $id): void
    {
        $this->writeTabbed('Result', 'Created', 11);
        $this->writeTabbed('DONL ID', $id, 11);
    }

    /**
     * Write that the creation of the dataset was rejected by the target catalog.
     *
     * @param string $message The error message
     */
    public function writeDatasetCreationRejected(string $message): void
    {
        $this->writeTabbed('Result', 'Rejected', 11);
        $this->writeTabbed(
            'Reason',
            'The target catalog rejected the dataset creation request',
            11
        );
        $this->writeTabbed('Error', $message, 11);
    }

    /**
     * Write that the dataset was not created because of a local database error.
     *
     * @param string $message The error message
     */
    public function writeDatasetCreationRejectedByDatabase(string $message): void
    {
        $this->writeTabbed('Result', 'Rejected', 11);
        $this->writeTabbed(
            'Reason',
            'Error interacting with the local database prevented the dataset creation',
            11
        );
        $this->writeTabbed('Error', $message, 11);
    }

    /**
     * Write that the SQL transaction failed to rollback properly.
     *
     * @param string $message The error message
     */
    public function writeDatasetTransactionFailedToAbort(string $message): void
    {
        $this->writeTabbed('Debug', $message);
    }

    /**
     * Writes the notices generated during the dataset conversion.
     *
     * @param string[] $notices The notices to print
     */
    public function writeDatasetNotices(array $notices): void
    {
        if (0 === count($notices)) {
            $this->writeTabbed('Notices', 'None', 11);

            return;
        }

        $first = true;

        foreach ($notices as $notice) {
            if ($first) {
                $this->writeTabbed('Notices', $notice, 11);
                $first = false;

                continue;
            }

            $this->writeTabbed('', $notice, 11);
        }
    }
}
