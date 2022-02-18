<?php

namespace DonlSync\Test\Unit;

use DonlSync\Application;
use DonlSync\Configuration;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\OutputHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApplicationTest extends TestCase
{
    private InputInterface $mocked_input;

    private OutputInterface $mocked_output;

    public function setUp(): void
    {
        parent::setUp();

        $this->mocked_input = new class() implements InputInterface {
            public function getFirstArgument()
            {
            }

            public function hasParameterOption($values, bool $onlyParams = false)
            {
            }

            public function getParameterOption($values, $default = false, bool $onlyParams = false)
            {
            }

            public function bind(InputDefinition $definition)
            {
            }

            public function validate()
            {
            }

            public function getArguments()
            {
            }

            public function getArgument(string $name)
            {
            }

            public function setArgument(string $name, $value)
            {
            }

            public function hasArgument($name)
            {
            }

            public function getOptions()
            {
            }

            public function getOption(string $name)
            {
            }

            public function setOption(string $name, $value)
            {
            }

            public function hasOption(string $name)
            {
            }

            public function isInteractive()
            {
            }

            public function setInteractive(bool $interactive)
            {
            }
        };

        $this->mocked_output = new class() implements OutputInterface {
            public function write($messages, bool $newline = false, int $options = 0)
            {
            }

            public function writeln($messages, int $options = 0)
            {
            }

            public function setVerbosity(int $level)
            {
            }

            public function getVerbosity()
            {
            }

            public function isQuiet()
            {
            }

            public function isVerbose()
            {
            }

            public function isVeryVerbose()
            {
            }

            public function isDebug()
            {
            }

            public function setDecorated(bool $decorated)
            {
            }

            public function isDecorated()
            {
            }

            public function setFormatter(OutputFormatterInterface $formatter)
            {
            }

            public function getFormatter()
            {
            }
        };
    }

    public function testAssignedIOInterfacesAreRetrievable(): void
    {
        $application = new Application($this->mocked_input, $this->mocked_output);

        $this->assertSame($this->mocked_input, $application->input());
        $this->assertSame($this->mocked_output, $application->output());
    }

    public function testSameBladeEngineIsReturnedOnSubsequentCalls(): void
    {
        $application = new Application($this->mocked_input, $this->mocked_output);
        $engine      = $application->blade_engine();

        $this->assertSame($engine, $application->blade_engine());
    }

    public function testTimerThrowsRuntimeExceptionOnMissingConfiguration(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage(
            'Missing or corrupt configuration for creating DateTimer object'
        );

        $application = new class($this->mocked_input, $this->mocked_output) extends Application {
            public function config(string $name): Configuration
            {
                throw new ConfigurationException();
            }
        };

        $application->timer();
    }

    public function testDifferentTimerIsReturnedOnSubsequentCalls(): void
    {
        $application = new Application($this->mocked_input, $this->mocked_output);
        $timer       = $application->timer();

        $this->assertNotSame($timer, $application->timer());
    }

    public function testVersionReturnsSameValueOnSubsequentCalls(): void
    {
        $checksum_file = Application::APP_ROOT . 'CHECKSUM';
        file_put_contents($checksum_file, 'FooBarBaz');
        register_shutdown_function('unlink', $checksum_file);

        $application = new Application($this->mocked_input, $this->mocked_output);
        $version     = $application->version();

        $this->assertSame($version, $application->version());
    }

    public function testOutputHelperReturnsProperInstance(): void
    {
        $application = new Application($this->mocked_input, $this->mocked_output);

        $this->assertInstanceOf(OutputHelper::class, $application->output_helper());
    }

    /**
     * @runInSeparateProcess
     */
    public function testCKANCredentialsReadsFromEnvironment(): void
    {
        $application = new Application($this->mocked_input, $this->mocked_output);

        $_ENV['CATALOG_FOO_OWNER_ORG'] = 'foo';
        $_ENV['CATALOG_FOO_USER_ID']   = 'bar';
        $_ENV['CATALOG_FOO_API_KEY']   = 'baz';

        $credentials = $application->ckan_credentials('foo');

        $this->assertEquals('foo', $credentials['owner_org']);
        $this->assertEquals('bar', $credentials['user_id']);
        $this->assertEquals('baz', $credentials['api_key']);
    }
}
