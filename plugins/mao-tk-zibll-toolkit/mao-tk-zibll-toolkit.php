<?php
/**
 * Plugin Name: Mao TK Zibll Toolkit
 * Description: 面向 Zibll 主题的模块化扩展工作台，提供独立后台与按需启用的模块。
 * Version: 1.24.0-rc.10
 * Author: Mao TK
 * Author URI: https://www.maotk.com/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mao-tk-zibll-toolkit
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('MAO_TK_ZIBLL_TOOLKIT_VERSION', '1.24.0-rc.10');
define('MAO_TK_ZIBLL_TOOLKIT_FILE', __FILE__);
define('MAO_TK_ZIBLL_TOOLKIT_DIR', plugin_dir_path(__FILE__));
define('MAO_TK_ZIBLL_TOOLKIT_URL', plugin_dir_url(__FILE__));

require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/interface-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-loading-ui.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-theme-compatibility.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-native-markup-compatibility.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-document-title-compatibility.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-admin-notice-compatibility.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-module-manager.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-welcome-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-article-data.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-editor.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-content-search.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/top-info/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-top-info-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/freshness/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/freshness/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/freshness/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-article-freshness-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-ui/class-catalog.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-ui/class-content.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-ui/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-ui/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-article-ui-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-note/class-icons.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-note/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-note/class-visual.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-note/class-integration.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/article-note/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-article-note-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-media.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-integration.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/showcase/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-showcase-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/private-links/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/private-links/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/private-links/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-private-links-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/maintenance/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/maintenance/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/maintenance/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/maintenance/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-maintenance-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/profile-presets/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/profile-presets/class-catalog.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/profile-presets/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/profile-presets/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-profile-presets-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/class-orders.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-order-query-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/class-provider.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/not-found/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-not-found-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/migration/class-store.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/migration/class-package.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/migration/class-links.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/migration/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-links-migration-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/post-migration/class-store.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/post-migration/class-package.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/post-migration/class-markup.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/post-migration/class-posts.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/post-migration/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-posts-migration-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-store.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-schema.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-package.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-markup.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-products.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/product-migration/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-products-migration-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-catalog.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-source.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/system-mail/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-system-mail-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/custom-footer/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/custom-footer/class-data.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/custom-footer/class-renderer.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/custom-footer/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/custom-footer/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-custom-footer-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/archive-header/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/archive-header/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/archive-header/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-archive-header-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-storage.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-uploads.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-public-download.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-session.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-downloads/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-r2-downloads-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-media/class-settings.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-media/class-files.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-media/class-sync.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-media/class-runtime.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/r2-media/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/modules/class-r2-media-module.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/config-backup/class-registry.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/config-backup/class-assets.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/config-backup/class-store.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/config-backup/class-service.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/config-backup/class-admin-page.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-admin.php';
require_once MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/class-plugin.php';

// Registration ownership and editor filters must exist before any init block provider.
MaoTK\ZibllToolkit\ArticleNote\Integration::instance()->register();

register_activation_hook(__FILE__, array('MaoTK\\ZibllToolkit\\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('MaoTK\\ZibllToolkit\\R2Media\\Runtime', 'stop'));
add_action('after_setup_theme', static function (): void {
    load_plugin_textdomain('mao-tk-zibll-toolkit', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 19);
add_action('after_setup_theme', array('MaoTK\\ZibllToolkit\\Plugin', 'boot'), 20);
