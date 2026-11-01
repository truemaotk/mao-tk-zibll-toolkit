<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

/** Bridge Zibll's disabled legacy SEO output to Rank Math's core title renderer. */
final class DocumentTitleCompatibility
{
    public static function register(): void
    {
        // Called during after_setup_theme, after the parent/child theme has loaded.
        // Do not take ownership from a theme that still renders its own title.
        if (is_admin() || get_template() !== 'zibll' || !defined('RANK_MATH_VERSION')
            || !function_exists('_pz') || current_theme_supports('title-tag')
            || _pz('post_keywords_description_s') || apply_filters('echo_seo_title', false)) {
            return;
        }

        // Rank Math moves the core renderer to rank_math/head and supplies the
        // title via pre_get_document_title. No new head output or robots filters.
        add_theme_support('title-tag');
    }
}
