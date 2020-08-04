<?php

namespace DonlSync\Command;

use DonlSync\Application;
use DonlSync\Database\Repository\ExecutionMessageRepository as EMR;
use DonlSync\Database\Repository\ProcessedDatasetsRepository as PDR;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DatabaseInstallerCommand.
 *
 * Installs all database tables, assuming they do not exist yet.
 */
class DatabaseInstallerCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('InstallDatabase');
        $this->setDescription(
            'Ensures that all database are present'
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
        $connection  = $application->database_connection();

        $tables = [
            PDR::TABLE_NAME => PDR::class,
            EMR::TABLE_NAME => EMR::class,
        ];

        foreach ($tables as $table => $class) {
            $output->writeln('Table ' . $table);

            call_user_func([$class, 'createTable'], $connection);

            $output->writeln(' > OK');
            $output->writeln('');
        }

        return 0;
    }
}
