<?php

namespace DonlSync\Command;

use DonlSync\Application;
use DonlSync\Database\DatabaseAnalyzerBuilder;
use DonlSync\Exception\InputException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DatabaseAnalyzerCommand.
 *
 * Instructs DONLSync to analyze and repair the database contents for a specific catalog.
 */
class DatabaseAnalyzerCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('AnalyzeDatabase');
        $this->setDescription(
            'Analyzes and repairs the database contents for a specific catalog'
        );
        $this->addOption(
            'catalog', null, InputOption::VALUE_REQUIRED,
            'The catalog to analyze and ,if needed, repair'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception Thrown on any unrecoverable error
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = new Application($input, $output);
        $catalog     = $input->getOption('catalog');

        if (null === $catalog || '' == trim($catalog)) {
            throw new InputException('catalog argument is missing or empty');
        }

        $target = $application->target_catalog();
        $source = $application->source_catalog($catalog);

        $output_helper = $application->output_helper();
        $output_helper->writeAnalyzerHeader($target);

        $database_analyzer = (new DatabaseAnalyzerBuilder())
            ->withDatabaseConnection($application->database_connection())
            ->withOutputHelper($output_helper)
            ->withTargetCatalog($target)
            ->withSourceCatalog($source)
            ->build();
        $database_analyzer->analyze();

        return 0;
    }
}
