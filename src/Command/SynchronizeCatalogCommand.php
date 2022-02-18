<?php

namespace DonlSync\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use DonlSync\Application;
use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Source\ISourceCatalog;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Database\DatabaseAnalyzerBuilder;
use DonlSync\Database\Repository\ExecutionMessageRepository as EMR;
use DonlSync\Database\Repository\ProcessedDatasetsRepository as PDR;
use DonlSync\Database\Repository\UnmappedValuesRepository;
use DonlSync\Dataset\Builder\DatasetBuilderBuilder;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogPublicationException;
use DonlSync\Exception\DatabaseAnalyzerException;
use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\OutputHelper;
use DonlSync\Helper\Summarizer;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SynchronizeCatalogCommand.
 *
 * Instructs DonlSync to synchronize a catalog with the given execution environment.
 */
class SynchronizeCatalogCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('SynchronizeCatalog');
        $this->setDescription('Synchronize a source catalog to data.overheid.nl');
        $this->addOption('catalog', 'c', InputOption::VALUE_REQUIRED,
            'The catalog to synchronize'
        );
        $this->addOption(
            'no-analyze', 'na', InputOption::VALUE_NONE,
            'To disable the database analyzer'
        );
        $this->addOption(
            'scheduled', 's', InputOption::VALUE_OPTIONAL,
            'The date of the scheduled execution'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception Thrown on any unrecoverable error
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $application   = new Application($input, $output);
        $no_analyze    = $input->getOption('no-analyze');
        $catalog       = $input->getOption('catalog');
        $scheduled     = $input->getOption('scheduled');
        $output_helper = $application->output_helper();
        $source        = $application->source_catalog($catalog);
        $target        = $application->target_catalog();
        $stopwatch     = $application->timer();
        $connection    = $application->database_connection();

        UnmappedValuesRepository::clearTable($connection);

        $summarizer_file = ApplicationInterface::LOG_DIR . 'summary__' . $scheduled . '.json';
        $summarizer      = (empty($scheduled))
            ? new Summarizer()
            : Summarizer::fromFile($summarizer_file);

        $stopwatch->start();
        $output_helper->writeHeader(
            $application->config('catalog_' . $catalog)->all(),
            $application->config('catalog_DONL')->all(),
            $stopwatch
        );

        if (!$no_analyze) {
            $this->analyzeDatabase($connection, $output_helper, $source, $target);
        }

        $identifier_history = [];
        $processed_datasets = [];
        $catalog_slug       = $source->getCatalogSlugName();
        $known_datasets     = PDR::getRecordsByCatalogName($connection, $catalog_slug);

        try {
            $credentials     = $application->ckan_credentials($catalog_slug);
            $catalog_data    = $source->getData();
            $dataset_builder = DatasetBuilderBuilder::buildFromSourceCatalog($source);

            $output_helper->writeDataFound(count($catalog_data), $source->getCatalogEndpoint());

            foreach ($catalog_data as $potential_dataset) {
                if (empty($potential_dataset['identifier'])) {
                    continue;
                }

                $identifier = $potential_dataset['identifier'];
                $output_helper->writeDatasetIntro($potential_dataset, $target, $known_datasets);

                if (in_array($identifier, $identifier_history)) {
                    $this->processIdentifierConflict(
                        $output_helper, $summarizer, $source, $connection, $identifier
                    );

                    continue;
                }

                $identifier_history[] = $identifier;
                $converted_dataset    = $dataset_builder->buildDataset(
                    $catalog_slug, $potential_dataset
                );

                $output_helper->writeDatasetNotices($converted_dataset->getConversionNotices());
                $this->determineTargetIdentifierIfApplicable($converted_dataset, $known_datasets);

                $validation_result = $converted_dataset->getDataset()->validate();

                if (!$validation_result->validated()) {
                    $summarizer->incrementKey('discarded_datasets');
                    $output_helper->writeDatasetInvalid($validation_result->getMessages());

                    continue;
                }

                $summarizer->incrementKey('validated_datasets');
                $processed_datasets[] = $converted_dataset->getCatalogIdentifier();
                $output_helper->writeDatasetSynchronizeAction();

                if (null === $converted_dataset->getTargetIdentifier()) {
                    $this->createDatasetRoutine(
                        $converted_dataset, $target, $summarizer, $output_helper, $connection,
                        $credentials
                    );
                } else {
                    $this->updateDatasetRoutine(
                        $converted_dataset, $known_datasets, $target, $summarizer, $output_helper,
                        $connection, $credentials
                    );
                }
            }

            $this->removeAbsentDatasets(
                $processed_datasets, $source, $target, $summarizer, $output_helper, $connection,
                $credentials
            );
        } catch (CatalogHarvestingException $e) {
            $this->processHarvestingFailure($e, $connection, $output_helper, $source);
        }

        $stopwatch->end();
        $output_helper->writeSummary($summarizer, $stopwatch);

        if ($scheduled) {
            $summarizer->writeToFile($summarizer_file, $source->getCatalogSlugName());

            try {
                UnmappedValuesRepository::recordsToFile(sprintf('%s/%s__unmapped__%s.log',
                    ApplicationInterface::LOG_DIR,
                    $catalog_slug,
                    $scheduled
                ));
            } catch (\Doctrine\DBAL\Exception $e) {
                // Fail silently.
            }
        }

        return 0;
    }

    /**
     * Deletes all datasets from the target catalog which no longer exist on the source catalog.
     *
     * @param string[]       $datasets    The datasets on the source catalog
     * @param ISourceCatalog $catalog     The source catalog
     * @param ITargetCatalog $target      The target catalog
     * @param Summarizer     $summary     The execution summary so far
     * @param OutputHelper   $helper      For writing to the output
     * @param Connection     $connection  The database connection
     * @param string[]       $credentials The CKAN credentials
     */
    public function removeAbsentDatasets(array $datasets, ISourceCatalog $catalog,
                                         ITargetCatalog $target, Summarizer $summary,
                                         OutputHelper $helper, Connection $connection,
                                         array $credentials): void
    {
        $threshold = $_ENV['CATALOG_TARGET_DELETION_THRESHOLD'];

        try {
            $datasets_on_target = $target->getData($credentials);
            $datasets_to_delete = [];
            $delete_datasets    = true;

            foreach ($datasets_on_target as $target_dataset) {
                if (!in_array($target_dataset['identifier'], $datasets)) {
                    $datasets_to_delete[] = $target_dataset;
                }
            }

            $helper->writeDatasetDeletionIntroduction(count($datasets_to_delete));

            if (0 === count($datasets_to_delete)) {
                return;
            }

            $deletion_threshold = (int) floor($threshold * count($datasets_on_target));

            if (count($datasets_to_delete) > $deletion_threshold) {
                $delete_datasets = false;
                $helper->writeExceededDatasetDeletionThreshold($deletion_threshold, $threshold);

                try {
                    EMR::insertRecord($connection, [
                        'message' => sprintf(
                            '%s: Datasets to delete exceeded the configured threshold ' .
                            '(over %s%% of total catalog volume), dataset deletion was disabled ' .
                            'for this catalog',
                            $catalog->getCatalogSlugName(), $threshold * 100
                        ),
                    ]);
                } catch (DBALException $e) {
                }
            }

            foreach ($datasets_to_delete as $dataset) {
                $helper->writeDatasetToDelete($dataset);

                if (!$delete_datasets) {
                    $helper->writeDatasetNotDeleted();

                    continue;
                }

                try {
                    $target->deleteDataset($dataset['id'], $credentials);
                    PDR::deleteRecordByTargetIdentifier($connection, $dataset['id']);
                    $summary->incrementKey('deleted_datasets');
                    $helper->writeDatasetDeleted();
                } catch (CatalogPublicationException $e) {
                    $helper->writeDatasetDeletionRejected($e->getMessage());
                } catch (DBALException $e) {
                    $helper->writeDatasetRecordDeletionFailure($e->getMessage());
                }
            }
        } catch (CatalogHarvestingException $e) {
            $helper->writeDatasetRemovalCancelled();
        }
    }

    /**
     * Executes the DatabaseAnalyzer for the source catalog.
     *
     * @param Connection     $connection The database connection
     * @param OutputHelper   $helper     The output helper for writing to the log
     * @param ISourceCatalog $source     The source catalog being harvested
     * @param ITargetCatalog $target     The catalog to which the datasets are sent
     *
     * @throws DatabaseAnalyzerException Thrown if the DatabaseAnalyzer encountered an unrecoverable
     *                                   error
     */
    private function analyzeDatabase(Connection $connection, OutputHelper $helper,
                                     ISourceCatalog $source, ITargetCatalog $target): void
    {
        $database_analyzer = (new DatabaseAnalyzerBuilder())
            ->withDatabaseConnection($connection)
            ->withOutputHelper($helper)
            ->withSourceCatalog($source)
            ->withTargetCatalog($target)
            ->build();
        $database_analyzer->analyze();
    }

    /**
     * Logs and registers an ExecutionMessage stating that a duplicate DCAT identifier was
     * encountered.
     *
     * @param OutputHelper   $output_helper The output helper for writing to the log
     * @param Summarizer     $summarizer    The import summary thus far
     * @param ISourceCatalog $source        The source catalog being harvested
     * @param Connection     $connection    The active database connection
     * @param string         $identifier    The conflicting identifier
     */
    private function processIdentifierConflict(OutputHelper $output_helper, Summarizer $summarizer,
                                               ISourceCatalog $source, Connection $connection,
                                               string $identifier): void
    {
        $output_helper->writeDatasetIdentifierConflict();
        $summarizer->incrementKey('conflict_datasets');

        try {
            EMR::insertRecord($connection, [
                'message' => sprintf('%s: Identifier conflict encountered for dataset %s',
                    $source->getCatalogSlugName(), $identifier
                ),
            ]);
        } catch (DBALException $e) {
            throw new DonlSyncRuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Processes a CatalogHarvestingException.
     *
     * @param CatalogHarvestingException $exception  The harvesting exception thrown
     * @param Connection                 $connection The active database connection
     * @param OutputHelper               $helper     The output helper
     * @param ISourceCatalog             $catalog    The catalog being harvested
     */
    private function processHarvestingFailure(CatalogHarvestingException $exception,
                                              Connection $connection, OutputHelper $helper,
                                              ISourceCatalog $catalog): void
    {
        $helper->writeDatasetSynchronizationCancelled($exception->getMessage());

        try {
            EMR::insertRecord($connection, [
                'message' => sprintf('%s: Harvesting failure; Error: %s',
                    $catalog->getCatalogSlugName(),
                    $exception->getMessage()
                ),
            ]);
        } catch (DBALException $e) {
            throw new DonlSyncRuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Registers an ExecutionMessage stating that the specified dataset was rejected by the target
     * catalog.
     *
     * @param Exception        $exception  The target rejection as an Exception
     * @param Connection       $connection The active database connection
     * @param DatasetContainer $dataset    The container holding the dataset which was rejected
     */
    private function registerTargetRejection(Exception $exception, Connection $connection,
                                             DatasetContainer $dataset): void
    {
        try {
            EMR::insertRecord($connection, [
                'message' => sprintf('%s: Dataset %s rejected by target; Error: %s',
                    $dataset->getCatalogName(),
                    $dataset->getDataset()->getIdentifier()->getData(),
                    $exception->getMessage()
                ),
            ]);
        } catch (DBALException $exception) {
            throw new DonlSyncRuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Determines if a dataset already exists on the target catalog and assigns the given target
     * identifier if that is the case.
     *
     * @param DatasetContainer  $container      The dataset container to analyse
     * @param array<int, array> $known_datasets All datasets from the source catalog published on the
     *                                          target
     */
    private function determineTargetIdentifierIfApplicable(DatasetContainer $container,
                                                           array $known_datasets): void
    {
        foreach ($known_datasets as $known_dataset) {
            if ($container->getCatalogIdentifier() === $known_dataset['catalog_identifier']) {
                $container->setTargetIdentifier($known_dataset['target_identifier']);
            }
        }
    }

    /**
     * Attempts to create the given dataset on the target catalog.
     *
     * @param DatasetContainer $dataset     The dataset to create
     * @param ITargetCatalog   $catalog     The target catalog
     * @param Summarizer       $summarizer  The execution summary so far
     * @param OutputHelper     $helper      For writing to the output
     * @param Connection       $connection  The active database connection
     * @param string[]         $credentials The CKAN credentials
     */
    private function createDatasetRoutine(DatasetContainer $dataset, ITargetCatalog $catalog,
                                          Summarizer $summarizer, OutputHelper $helper,
                                          Connection $connection, array $credentials): void
    {
        try {
            $connection->beginTransaction();

            PDR::createMinimalRecord($connection, $dataset);
            $assigned_number = PDR::getAssignedNumberByCatalogNameAndIdentifier(
                $connection, $dataset->getCatalogName(), $dataset->getCatalogIdentifier()
            );

            $dataset->setAssignedNumber($assigned_number);
            $dataset->generateHash();

            $id = $catalog->publishDataset($dataset, $credentials);
            $dataset->setTargetIdentifier($id);

            PDR::updateRecordFully($connection, $dataset);

            $connection->commit();

            $summarizer->incrementKey('created_datasets');
            $helper->writeDatasetCreated($dataset->getTargetIdentifier());
        } catch (CatalogPublicationException | DBALException $e) {
            $summarizer->incrementKey('rejected_datasets');
            $output_method = $e instanceof DBALException
                ? 'writeDatasetCreationRejectedByDatabase'
                : 'writeDatasetCreationRejected';

            $helper->$output_method($e->getMessage());

            try {
                $connection->rollBack();
            } catch (DBALException $e) {
                $helper->writeDatasetTransactionFailedToAbort($e->getMessage());
            }

            $this->registerTargetRejection($e, $connection, $dataset);
        }
    }

    /**
     * Attempts to update a dataset on the target catalog, should an update be required.
     *
     * @param DatasetContainer  $dataset            The dataset to update
     * @param array<int, array> $datasets_on_target The datasets on the target catalog
     * @param ITargetCatalog    $catalog            The target catalog
     * @param Summarizer        $summarizer         The execution summary so far
     * @param OutputHelper      $helper             For writing to the output
     * @param Connection        $connection         The database connection
     * @param string[]          $credentials        The CKAN credentials
     */
    private function updateDatasetRoutine(DatasetContainer $dataset, array $datasets_on_target,
                                          ITargetCatalog $catalog, Summarizer $summarizer,
                                          OutputHelper $helper, Connection $connection,
                                          array $credentials): void
    {
        $dataset_on_database = null;
        $dataset->generateHash();

        foreach ($datasets_on_target as $target_dataset) {
            $target_identifier = $target_dataset['target_identifier'];

            if ($dataset->getTargetIdentifier() === $target_identifier) {
                $dataset_on_database = $target_dataset;

                break;
            }
        }

        if ($dataset->getDatasetHash() === $dataset_on_database['dataset_hash']) {
            $summarizer->incrementKey('ignored_datasets');
            $helper->writeDatasetIgnored();

            return;
        }

        try {
            $dataset->setAssignedNumber($dataset_on_database['assigned_number']);
            $catalog->updateDataset($dataset, $credentials);
            PDR::updateRecord($connection, $dataset);
            $helper->writeDatasetUpdated();
            $summarizer->incrementKey('updated_datasets');
        } catch (CatalogPublicationException $e) {
            $summarizer->incrementKey('rejected_datasets');
            $helper->writeDatasetUpdateRejected($e->getMessage());

            $this->registerTargetRejection($e, $connection, $dataset);
        } catch (DBALException $e) {
            $summarizer->incrementKey('rejected_datasets');
            $helper->writeDatasetUpdatedButNotTheDatabase($e->getMessage());

            $this->registerTargetRejection($e, $connection, $dataset);
        }
    }
}
