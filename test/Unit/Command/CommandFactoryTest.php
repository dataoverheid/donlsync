<?php

namespace DonlSync\Test\Unit\Command;

use DonlSync\Command\CommandFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

class CommandFactoryTest extends TestCase
{
    public function testGetCommandsReturnsOnlyCommandImplementations(): void
    {
        $commands = CommandFactory::getCommands();

        foreach ($commands as $command) {
            $this->assertInstanceOf(Command::class, $command);
        }
    }
}
