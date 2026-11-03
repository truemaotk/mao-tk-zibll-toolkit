<?php
namespace MaoTK\ZibllToolkit\CustomFooter;

defined('ABSPATH') || exit;

/** Uses WordPress read APIs. No custom SQL, role overrides, option writes or cron. */
final class Data
{
    public static function bookmarks(): array
    {
        if (!function_exists('get_bookmarks')) { return array(); }
        $links = get_bookmarks(array(
            'category' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'hide_invisible' => 1,
            'show_updated' => 0,
            'include' => '',
            'exclude' => '',
        ));
        // Existing Toolkit private-link filters remain in charge of visibility.
        return is_array($links) ? array_values(array_filter($links, 'is_object')) : array();
    }

    public static function performance(): array
    {
        return array(
            'queries' => function_exists('get_num_queries') ? (int) get_num_queries() : null,
            'seconds' => function_exists('timer_stop') ? (string) timer_stop(0, 3) : null,
        );
    }
}
