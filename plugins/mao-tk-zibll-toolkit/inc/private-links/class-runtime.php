<?php
namespace MaoTK\ZibllToolkit\PrivateLinks;

defined('ABSPATH') || exit;

final class Runtime
{
    private static $registered = false;
    private static $querying = false;

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered = true;
        add_filter('get_bookmarks', array(__CLASS__, 'bookmarks'), PHP_INT_MAX, 2);
        add_filter('query', array(__CLASS__, 'native_query'), PHP_INT_MAX);
        add_action('init', array(__CLASS__, 'prevent_cache'), 1);
        add_filter('wp_headers', array(__CLASS__, 'headers'), PHP_INT_MAX);
        add_action('template_redirect', array(__CLASS__, 'private_detail'), 4);
    }

    private static function backend(): bool
    {
        return is_admin() && !wp_doing_ajax();
    }

    private static function front_request(): bool
    {
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron()
            && !(defined('REST_REQUEST') && REST_REQUEST)
            && !(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            && !(defined('WP_CLI') && WP_CLI)
            && !(function_exists('is_feed') && is_feed());
    }

    public static function allowed(): bool
    {
        return self::front_request() && (new Settings())->allows_current_user();
    }

    /** Core caches raw queries before this filter; never mutate cached bookmark objects. */
    public static function bookmarks($bookmarks, $args): array
    {
        $bookmarks = is_array($bookmarks) ? $bookmarks : array();
        if (self::backend()) { return $bookmarks; }
        if (!is_array($args)) { return array(); }
        if (self::allowed()) {
            // A missing category name is resolved to false by core. Do not turn it into all links.
            if (!empty($args['category_name']) && empty($args['category'])) { return array(); }
            if (!self::$querying && !empty($args['hide_invisible'])) {
                self::$querying = true;
                try { return get_bookmarks(array_replace($args, array('hide_invisible' => false))); }
                finally { self::$querying = false; }
            }
            return array_values(array_filter($bookmarks, static function ($link): bool { return is_object($link) && in_array($link->link_visible ?? '', array('Y', 'N'), true); }));
        }
        return array_values(array_filter($bookmarks, static function ($link): bool { return is_object($link) && ($link->link_visible ?? '') === 'Y'; }));
    }

    /** Zibll 9.2's DISTINCT multi-category path bypasses get_bookmarks(). */
    public static function native_query(string $sql): string
    {
        if (self::backend()) { return $sql; }
        global $wpdb;
        if (!isset($wpdb->links, $wpdb->term_relationships, $wpdb->term_taxonomy)) { return $sql; }
        $prefix = 'SELECT DISTINCT l.* FROM ' . $wpdb->links . ' AS l';
        if (strpos($sql, $prefix) !== 0
            || strpos($sql, 'INNER JOIN ' . $wpdb->term_relationships . ' AS tr ON l.link_id = tr.object_id') === false
            || strpos($sql, 'INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id') === false
            || strpos($sql, "tt.taxonomy = 'link_category'") === false) { return $sql; }
        $native = false;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16) as $frame) {
            if (($frame['function'] ?? '') === 'zib_get_bookmarks' && empty($frame['class'])) { $native = true; break; }
        }
        if (!$native) { return $sql; }
        $predicate = self::allowed() ? "l.link_visible IN ('Y','N')" : "l.link_visible = 'Y'";
        if (strpos($sql, "l.link_visible = 'Y'") !== false) { return str_replace("l.link_visible = 'Y'", $predicate, $sql); }
        return preg_replace('/\bWHERE\s+/', 'WHERE ' . $predicate . ' AND ', $sql, 1) ?? $sql;
    }

    public static function prevent_cache(): void
    {
        if (self::allowed() && !defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
    }

    public static function headers(array $headers): array
    {
        if (!self::allowed()) { return $headers; }
        $headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
        $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
        $headers['Vary'] = isset($headers['Vary']) ? $headers['Vary'] . ', Cookie' : 'Cookie';
        return $headers;
    }

    /** Use the installed native detail template; the database and bookmark cache stay N. */
    public static function private_detail(): void
    {
        if (!self::allowed() || !function_exists('zib_link_detail_template') || !function_exists('zib_str_cut') || !function_exists('_get_delimiter') || !function_exists('zib_get_delimiter_blog_name')) { return; }
        $id = (int) get_query_var('link_id');
        if ($id < 1) { return; }
        $bookmark = get_bookmark($id);
        if (!is_object($bookmark) || empty($bookmark->link_id) || ($bookmark->link_visible ?? '') !== 'N') { return; }
        $template = get_theme_file_path('/pages/link.php');
        if (!is_readable($template) || !defined('ZIB_TEMPLATE_DIRECTORY_URI') || !defined('THEME_VERSION')) { return; }
        global $wp_query, $new_title, $new_description, $new_keywords;
        $wp_query->is_404 = false; $wp_query->is_home = false;
        $GLOBALS['zib_link_bookmark'] = $bookmark;
        $name = (string) $bookmark->link_name; $description = (string) $bookmark->link_description;
        $new_title = $name . ($description !== '' ? _get_delimiter() . zib_str_cut($description, 20) : '') . zib_get_delimiter_blog_name();
        $new_description = $description !== '' ? zib_str_cut($description, 150) : $new_title;
        $tags = get_the_terms($id, 'link_category');
        $new_keywords = is_array($tags) ? implode(',', array_column($tags, 'name')) : '';
        status_header(200); nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
        wp_enqueue_style('zib-page-link', ZIB_TEMPLATE_DIRECTORY_URI . '/css/page-link.min.css', array(), THEME_VERSION);
        load_template($template);
        exit;
    }
}
