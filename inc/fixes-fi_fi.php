<?php
defined('ABSPATH') || die;

/*
 * Fixes Finnish date formatting for the 'fi_FI' locale.
 */
add_filter('wp_date', function ($date, $format, $timestamp, $timezone) {
    $locale = determine_locale();

    if ('fi_FI' !== $locale && 'fi' !== $locale) {
        return $date;
    }

    if (false === strpos($format, 'F')) {
        return $date;
    }

    return wpu_pll_utilities_fix_fi_fi_date($date, $format, $timestamp, $timezone);

}, 10, 4);

/**
 * Fixes Finnish date formatting for the 'fi_FI' locale.
 *
 * @param string $date The formatted date string.
 * @param string $format The date format.
 * @param int $timestamp The Unix timestamp.
 * @param DateTimeZone $timezone The timezone.
 * @return string The corrected date string.
 */
function wpu_pll_utilities_fix_fi_fi_date($date, $format, $timestamp, $timezone) {

    $months = [
        1 => 'tammikuuta',
        2 => 'helmikuuta',
        3 => 'maaliskuuta',
        4 => 'huhtikuuta',
        5 => 'toukokuuta',
        6 => 'kesäkuuta',
        7 => 'heinäkuuta',
        8 => 'elokuuta',
        9 => 'syyskuuta',
        10 => 'lokakuuta',
        11 => 'marraskuuta',
        12 => 'joulukuuta'
    ];

    $datetime = (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone($timezone);

    $month_number = (int) $datetime->format('n');

    if (!isset($months[$month_number])) {
        return $date;
    }

    global $wp_locale;

    $month_key = $datetime->format('m');

    // strtr matches longest key first: genitive "kesäkuun" wins over nominative "kesäkuu", etc
    return strtr($date, array_filter([
        $wp_locale->get_month($month_key) => $months[$month_number],
        $wp_locale->month_genitive[$month_key] ?? '' => $months[$month_number],
        // Identity entry: keeps an already-correct form untouched
        $months[$month_number] => $months[$month_number]
    ], 'strlen', ARRAY_FILTER_USE_KEY));
}
