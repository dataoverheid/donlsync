<?php

namespace DonlSync\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Class CommandFactory.
 *
 * Instantiates commands for the DonlSync application.
 */
class CommandFactory
{
    /**
     * Returns all the commands defined for the DonlSync application.
     *
     * @return Command[] The commands of DonlSync
     */
    public static function getCommands(): array
    {
        return [
            new DatabaseInstallerCommand(),
            new DatabaseAnalyzerCommand(),
            new SynchronizeCatalogCommand(),
            new SendLogsCommand(),
        ];
    }
}
