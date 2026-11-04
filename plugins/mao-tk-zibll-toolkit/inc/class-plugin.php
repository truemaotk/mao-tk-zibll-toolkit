<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

final class Plugin
{
    private static $booted = false;

    public static function activate(bool $network_wide = false): void
    {
        if ($network_wide) {
            wp_die(
                esc_html__('V1 暂不提供网络级启用，请在需要的单个站点中启用插件。', 'mao-tk-zibll-toolkit'),
                '',
                array('response' => 400, 'back_link' => true)
            );
        }
        // add_option preserves existing settings across reactivation.
        add_option(ModuleManager::OPTION_KEY, ModuleManager::defaults(), '', false);
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        $theme = (new ThemeCompatibility())->inspect();
        if ($theme['is_zibll']) {
            NativeMarkupCompatibility::register();
            DocumentTitleCompatibility::register();
            if (is_admin()) { AdminNoticeCompatibility::register(); }
        }
        $manager = new ModuleManager();
        $manager->register(new Modules\WelcomeModule());
        $manager->register(new Modules\TopInfoModule());
        $manager->register(new Modules\ArticleFreshnessModule());
        foreach (ArticleUI\Catalog::all() as $key => $component) { $manager->register(new Modules\ArticleUIModule($key)); }
        $manager->register(new Modules\ArticleNoteModule());
        $manager->register(new Modules\ShowcaseModule());
        $manager->register(new Modules\PrivateLinksModule());
        $manager->register(new Modules\MaintenanceModule());
        $manager->register(new Modules\ProfilePresetsModule());
        $manager->register(new Modules\OrderQueryModule());
        $manager->register(new Modules\NotFoundModule());
        $manager->register(new Modules\LinksMigrationModule());
        $manager->register(new Modules\PostsMigrationModule());
        $manager->register(new Modules\ProductsMigrationModule());
        $manager->register(new Modules\SystemMailModule());
        $manager->register(new Modules\CustomFooterModule());
        $manager->register(new Modules\ArchiveHeaderModule());
        Showcase\Integration::register();
        OrderQuery\Runtime::register();
        NotFound\Runtime::register();
        $extension_failed = false;
        try {
            /** Register modules here. Callback receives ModuleManager. */
            do_action('mao_tk_zibll_toolkit_register_modules', $manager);
        } catch (\Throwable $error) {
            $extension_failed = true;
        }
        $manager->start($theme);
        if (is_admin()) {
            $admin = new Admin($manager, $theme, $extension_failed);
            $admin->register();
            // Settings/preview are a control plane, independent of enabled front-end modules.
            (new TopInfo\AdminPage($manager, $admin))->register();
            (new Freshness\AdminPage($manager, $admin))->register();
            (new ArticleUI\AdminPage($manager, $admin))->register();
            (new ArticleNote\AdminPage($manager, $admin))->register();
            (new Showcase\AdminPage($manager, $admin))->register();
            (new PrivateLinks\AdminPage($manager, $admin))->register();
            (new Maintenance\AdminPage($manager, $admin))->register();
            (new ProfilePresets\AdminPage($manager, $admin))->register();
            (new OrderQuery\AdminPage($manager, $admin))->register();
            (new NotFound\AdminPage($manager, $admin))->register();
            (new Migration\AdminPage($manager, $admin))->register();
            (new PostMigration\AdminPage($manager, $admin))->register();
            (new ProductMigration\AdminPage($manager, $admin))->register();
            (new SystemMail\AdminPage($manager, $admin))->register();
            (new CustomFooter\AdminPage($manager, $admin))->register();
            (new ArchiveHeader\AdminPage($manager, $admin))->register();
            (new ConfigBackup\AdminPage($admin))->register();
            if (!TopInfo\Runtime::conflict()) { (new TopInfo\Editor())->register(); }
        }
    }
}
