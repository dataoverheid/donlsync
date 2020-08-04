<?php

namespace DonlSync\Helper;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Class DateTimer.
 *
 * Simple timer implementation that works based on the \DateTime class.
 */
class DateTimer
{
    /** @var array */
    protected $date_config;

    /** @var DateTimeZone */
    protected $timezone;

    /** @var DateTime */
    protected $started;

    /** @var DateTime */
    protected $ended;

    /**
     * DateTimer constructor.
     *
     * @param array $date_config The date configuration to use
     */
    public function __construct(array $date_config)
    {
        $this->date_config = $date_config;
        $this->timezone    = new DateTimeZone($this->date_config['timezone']);
    }

    /**
     * Starts the timer.
     */
    public function start(): void
    {
        try {
            $this->started = new DateTime('now', $this->timezone);
        } catch (Exception $e) {
        }
    }

    /**
     * Stops the timer.
     */
    public function end(): void
    {
        try {
            $this->ended = new DateTime('now', $this->timezone);
        } catch (Exception $e) {
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
     * @return DateTime The started property
     */
    public function getStartTime(): DateTime
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
     * @return DateTime The ended property
     */
    public function getEndTime(): DateTime
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
