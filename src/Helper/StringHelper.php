<?php

namespace DonlSync\Helper;

/**
 * Class StringHelper.
 *
 * Common useful string functions.
 */
class StringHelper
{
    /**
     * StringHelper constructor.
     */
    private function __construct()
    {
    }

    /**
     * Replaces the start of a string assuming it matches the given pattern with the given
     * replacement value. If the start of the string does not match the given pattern, no action is
     * taken.
     *
     * @param string $input       The string to process
     * @param string $pattern     The pattern the input should start with
     * @param string $replacement The string that should replace the pattern
     *
     * @return string The, possibly modified, input
     */
    public static function ltrim(string $input, string $pattern, string $replacement = ''): string
    {
        if (mb_substr($input, 0, mb_strlen($pattern)) === $pattern) {
            return $replacement . mb_substr($input, mb_strlen($pattern));
        }

        return $input;
    }

    /**
     * Formats a 'date' string such as '20200709' into the format of `{year}/{month}/{day}`.
     *
     * @param string $input The string to format
     *
     * @return string The formatted string
     */
    public static function formatNonDateString(string $input): string
    {
        return sprintf('%s/%s/%s',
            mb_substr($input, 6, 2),
            mb_substr($input, 4, 2),
            mb_substr($input, 0, 4)
        );
    }
}
