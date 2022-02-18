<?php

namespace DonlSync\Test\Unit\Helper;

use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\DateTimer;
use PHPUnit\Framework\TestCase;

class DateTimerTest extends TestCase
{
    private array $config;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->config = [
            'timezone'        => 'Europe/Amsterdam',
            'format'          => 'd-m-Y H:i:s',
            'duration_format' => '%H hours, %i minutes, %s seconds',
        ];
    }

    public function testDonlSyncRuntimeExceptionIsThrownOnMissingConfigurationKeyTimezone(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Missing configuration key: timezone');

        new DateTimer([]);
    }

    public function testDonlSyncRuntimeExceptionIsThrownOnMissingConfigurationKeyFormat(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Missing configuration key: format');

        new DateTimer(['timezone' => 'foo']);
    }

    public function testDonlSyncRuntimeExceptionIsThrownOnMissingConfigurationKeyDuration(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Missing configuration key: duration_format');

        new DateTimer(['timezone' => 'foo', 'format' => 'bar']);
    }

    public function testDonlSyncRuntimeExceptionIsThrownOnInvalidTimezoneString(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('DateTimeZone::__construct(): Unknown or bad timezone (bar)');

        new DateTimer(['timezone' => 'bar', 'format' => 'foo', 'duration_format' => 'baz']);
    }

    public function testGetStartTimeReturnsNullWhenTimerNotStarted(): void
    {
        $timer = new DateTimer($this->config);

        $this->assertNull($timer->getStartTime());
    }

    public function testGetEndTimeReturnsNullWhenTimerNotEnded(): void
    {
        $timer = new DateTimer($this->config);

        $this->assertNull($timer->getEndTime());
    }

    /**
     * @runInSeparateProcess
     */
    public function testDonlSyncRuntimeExceptionIsThrownOnInvalidTimeStringWhenStarting(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);

        DateTimer::$NOW_STRING = 'n0w';
        $timer                 = new DateTimer($this->config);
        $timer->start();
    }

    /**
     * @runInSeparateProcess
     */
    public function testDonlSyncRuntimeExceptionIsThrownOnInvalidTimeStringWhenEnding(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('DateTime::__construct(): Failed to parse time string (n0w)');

        DateTimer::$NOW_STRING = 'now';

        $timer = new DateTimer($this->config);
        $timer->start();

        DateTimer::$NOW_STRING = 'n0w';

        $timer->end();
    }

    public function testEndThrowsDonlSyncRuntimeExceptionWhenTimerWasNotStarted(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('Cannot end timer that has not been started.');

        $timer = new DateTimer($this->config);
        $timer->end();
    }

    public function testDurationIsPositive(): void
    {
        $timer = new DateTimer($this->config);

        $timer->start();
        $timer->end();

        $this->assertEquals(1, $timer->getDuration()->invert);

        $timer->start();
        sleep(1);
        $timer->end();

        $this->assertEquals(1, $timer->getDuration()->invert);
    }

    public function testGivenFormatIsUsedWhenRequestingFormattedDateTimes(): void
    {
        $format   = '\f\o\o';
        $expected = 'foo';
        $timer    = new DateTimer($this->config);

        $timer->start();
        $timer->end();

        $this->assertEquals($expected, $timer->getStartTimeFormatted($format));
        $this->assertEquals($expected, $timer->getEndTimeFormatted($format));
        $this->assertEquals($format, $timer->getDurationFormatted($format));
    }

    public function testDefaultFormatIsUsedWhenRequestingFormattedDateTimesWithoutFormat(): void
    {
        $config                    = $this->config;
        $config['format']          = '\f\o\o';
        $config['duration_format'] = 'foo';
        $expected                  = 'foo';
        $timer                     = new DateTimer($config);

        $timer->start();
        $timer->end();

        $this->assertEquals($expected, $timer->getStartTimeFormatted());
        $this->assertEquals($expected, $timer->getEndTimeFormatted());
        $this->assertEquals($expected, $timer->getDurationFormatted());
    }
}
