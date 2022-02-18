<?php

namespace DonlSync\Test\Unit;

use DonlSync\Configuration;
use DonlSync\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testAllReturnsEntireDataset(): void
    {
        $test_data     = ['foo' => 'bar'];
        $configuration = new Configuration($test_data);

        $this->assertEqualsCanonicalizing($test_data, $configuration->all());
    }

    public function testGetThrowsExceptionOnNonExistentKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No configuration present with key bar');

        $test_data     = ['foo' => 'bar'];
        $configuration = new Configuration($test_data);
        $configuration->get('bar');
    }

    public function testGetReturnsDataWhenPresent(): void
    {
        try {
            $test_data     = ['foo' => 'bar'];
            $configuration = new Configuration($test_data);

            $this->assertEquals('bar', $configuration->get('foo'));
        } catch (ConfigurationException $e) {
            $this->fail('Unexpected ConfigurationException thrown');
        }
    }

    public function testComplexDatastructuresAreAllowed(): void
    {
        try {
            $test_data     = ['foo' => ['bar' => 'baz']];
            $configuration = new Configuration($test_data);

            $this->assertEquals(['bar' => 'baz'], $configuration->get('foo'));
        } catch (ConfigurationException $e) {
            $this->fail('Unexpected ConfigurationException thrown');
        }
    }

    public function testAddCanAddNewKeys(): void
    {
        try {
            $test_data     = ['foo' => 'bar'];
            $configuration = new Configuration($test_data);
            $configuration->add('bar', 'foo');

            $this->assertEquals('bar', $configuration->get('foo'));
            $this->assertEquals('foo', $configuration->get('bar'));
        } catch (ConfigurationException $e) {
            $this->fail('Unexpected ConfigurationException thrown');
        }
    }

    public function testAddOverwritesExistingKeys(): void
    {
        try {
            $test_data     = ['foo' => 'bar'];
            $configuration = new Configuration($test_data);

            $this->assertEquals('bar', $configuration->get('foo'));

            $configuration->add('foo', 'baz');

            $this->assertEquals('baz', $configuration->get('foo'));
        } catch (ConfigurationException $e) {
            $this->fail('Unexpected ConfigurationException thrown');
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testExceptionIsThrownOnUnreadableConfigurationFile(): void
    {
        // Mock PHP built-in
        eval('
            namespace DonlSync;

            function is_readable()
            {
                return false;
            }
        ');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cannot access configuration file at [ ' . __FILE__ . ' ]');

        Configuration::createFromJSONFile(__FILE__);
    }

    /**
     * @runInSeparateProcess
     */
    public function testExceptionIsThrownWhenFileContainsNoValidJson(): void
    {
        // Mock PHP built-in
        eval('
            namespace DonlSync;

            function is_readable()
            {
                return true;
            }
            
            function file_get_contents()
            {
                return null;
            }
        ');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Configuration file at [ ' . __FILE__ . ' ] contains invalid JSON'
        );

        Configuration::createFromJSONFile(__FILE__);
    }

    /**
     * @runInSeparateProcess
     */
    public function testExceptionIsThrownWhenFileContainsInvalidJson(): void
    {
        // Mock PHP built-in
        eval('
            namespace DonlSync;

            function is_readable()
            {
                return true;
            }
            
            function file_get_contents()
            {
                return \'{
                  ["}
                }\';
            }
        ');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Configuration file at [ ' . __FILE__ . ' ] contains invalid JSON'
        );

        Configuration::createFromJSONFile(__FILE__);
    }

    /**
     * @runInSeparateProcess
     */
    public function testConfigurationObjectIsCreatedFromJsonFile(): void
    {
        // Mock PHP built-in
        eval('
            namespace DonlSync;

            function is_readable()
            {
                return true;
            }
            
            function file_get_contents()
            {
                return \'{"foo": "bar"}\';
            }
        ');

        try {
            $config = Configuration::createFromJSONFile(__FILE__);

            $this->assertEquals('bar', $config->get('foo'));
        } catch (ConfigurationException $e) {
            $this->fail('Unexpected ConfigurationException thrown');
        }
    }
}
