<?php

namespace DonlSync\Helper;

use DateInterval;
use DateTime;
use DateTimeZone;
use DonlSync\Configuration;
use DonlSync\Exception\DonlSyncRuntimeException;
use Exception;

/**
 * Class DateTimer.
 *
 * Simple timer implementation that works based on the \DateTime class.
 */
class DateTimer
{
    /**
     * A string representation of the current datetime.
     */
    public static string $NOW_STRING = 'now';

    /**
     * The configuration data for datetime related operations.
     *
     * @var array<string, mixed>
     */
    protected array $date_config;

    /**
     * The timezone to use for all \DateTime related operations.
     */
    protected DateTimeZone $timezone;

    /**
     * The moment in time the timer was started. Will be initiated on the first request to
     * DateTimer::start().
     */
    protected ?DateTime $started;

    /**
     * The moment in time the timer ended. Will be initiated on the first request to
     * DateTimer::end().
     */
    protected ?DateTime $ended;

    /**
     * DateTimer constructor.
     *
     * @param string[] $date_config The date configuration to use
     */
    public function __construct(array $date_config)
    {
        Configuration::checkKeys($date_config, ['timezone', 'format', 'duration_format']);

        $this->date_config = $date_config;
        $this->started     = null;
        $this->ended       = null;

        try {
            $this->timezone = new DateTimeZone($this->date_config['timezone']);
        } catch (Exception $e) {
            throw new DonlSyncRuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Starts the timer.
     */
    public function start(): void
    {
        try {
            $this->started = new DateTime(self::$NOW_STRING, $this->timezone);
        } catch (Exception $e) {
            throw new DonlSyncRuntimeException($e->getMessage());
        }
    }

    /**
     * Stops the timer.
     */
    public function end(): void
    {
        if (null === $this->started) {
            throw new DonlSyncRuntimeException('Cannot end timer that has not been started.');
        }

        try {
            $this->ended = new DateTime(self::$NOW_STRING, $this->timezone);
        } catch (Exception $e) {
            throw new DonlSyncRuntimeException($e->getMessage());
        }
    }

    /**
     * Retrieves the difference between the end and start time as a DateInterval.
     *
     * @return DateInterval The difference between ended and started
     */
    public function getDuration(): DateInterval
    {
        return $this->ended->diff($this->started);
    }

    /**
     * Retrieves the duration of the DateTimer in a specified format.
     *
     * @param string $format The format to use
     *
     * @return string The formatted DateInterval
     */
    public function getDurationFormatted(string $format = ''): string
    {
        if (empty($format)) {
            $format = $this->date_config['duration_format'];
        }

        return $this->getDuration()->format($format);
    }

    /**
     * Getter for the started property.
     *
     * @return DateTime|null The started property
     */
    public function getStartTime(): ?DateTime
    {
        return $this->started;
    }

    /**
     * Getter for the started property formatted with the given format.
     *
     * @param string $format The format to use
     *
     * @return string The formatted started property
     */
    public function getStartTimeFormatted(string $format = ''): string
    {
        if (empty($format)) {
            $format = $this->date_config['format'];
        }

        return $this->getStartTime()->format($format);
    }

    /**
     * Getter for the ended property.
     *
     * @return DateTime|null The ended property
     */
    public function getEndTime(): ?DateTime
    {
        return $this->ended;
    }

    /**
     * Getter for the ended property formatted with the given format.
     *
     * @param string $format The format to use
     *
     * @return string The formatted ended property
     */
    public function getEndTimeFormatted(string $format = ''): string
    {
        if (empty($format)) {
            $format = $this->date_config['format'];
        }

        return $this->getEndTime()->format($format);
    }
}
