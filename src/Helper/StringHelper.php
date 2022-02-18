<?php

namespace DonlSync\Helper;

use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;

/**
 * Class StringHelper.
 *
 * Common useful string functions.
 */
class StringHelper
{
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
     * Formats a 'date' string such as '20200709' into the format of `{day}/{month}/{year}`.
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

    /**
     * Attempts to repair a given URL.
     *
     * - If the string starts with a `\`, then the starting `\` is removed
     * - If the string contains a pipe (`|`), then everything up to and including that pipe is
     *   removed from the string
     * - Replaces `http:\\` with the fallbackProtocol
     * - Replaces `https:\\` with the fallbackProtocol
     * - If the URL starts with `www`, it prepends the fallbackProtocol
     * - Spaces are replaced with `%20`
     * - '&amp;' is converted back to '&'
     * - If the URL does not start with either `http://` or `https://`, it prepends the
     *   fallbackProtocol
     *
     * @param string $url The url to repair
     *
     * @return string The original, possibly modified, url
     */
    public static function repairURL(string $url, string $fallbackProtocol = 'https://'): string
    {
        if (mb_strlen($url) > 0 && '\\' === mb_substr($url, 0, 1)) {
            $url = mb_substr($url, 1);
        }

        if (false !== mb_strpos($url, '|')) {
            $split = explode('|', $url);

            if (count($split) > 1) {
                $url = $split[1];
            }
        }

        $url = str_replace([
            'http:\\',
            'https:\\',
        ], $fallbackProtocol, $url);

        if ('www' === mb_substr($url, 0, 3)) {
            $url = $fallbackProtocol . $url;
        }

        $url = str_replace(' ', '%20', $url);
        $url = str_replace('&amp;', '&', $url);

        if (!empty($url) && false === mb_strpos($url, $fallbackProtocol)) {
            if (false === mb_strpos($url, 'http://')) {
                $url = $fallbackProtocol . $url;
            }
        }

        try {
            return (new Uri($url))->__toString();
        } catch (InvalidArgumentException $e) {
            return $url;
        }
    }
}
