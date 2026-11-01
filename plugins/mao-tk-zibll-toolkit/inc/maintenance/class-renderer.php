<?php
namespace MaoTK\ZibllToolkit\Maintenance;

defined('ABSPATH') || exit;

final class Renderer
{
    public static function respond(array $settings, bool $preview = false): void
    {
        status_header($preview ? 200 : 503);
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('Content-Type: text/html; charset=' . get_option('blog_charset', 'UTF-8'));
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' https: http:; style-src 'self'; script-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        if (!$preview) { header('Retry-After: ' . (int) $settings['retry_after']); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') { return; }
        self::render($settings, $preview);
    }

    public static function render(array $settings, bool $preview = false): void
    {
        $site_name = get_bloginfo('name');
        $deadline = Settings::deadline($settings['end_at']);
        $remaining = $deadline === null ? null : max(0, $deadline - time());
        $logo = $settings['logo_url'];
        if ($logo === '') {
            $attachment = (int) get_theme_mod('custom_logo', 0);
            $logo = $attachment ? (string) wp_get_attachment_image_url($attachment, 'full') : '';
            if ($logo === '') { $logo = (string) get_site_icon_url(128); }
        }
        $asset = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/maintenance/';
        $version = '?ver=' . rawurlencode(MAO_TK_ZIBLL_TOOLKIT_VERSION);
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/maintenance-page.php';
    }
}
