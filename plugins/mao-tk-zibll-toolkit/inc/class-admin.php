<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

final class Admin
{
    public const SLUG = 'mao-tk-zibll-toolkit';
    public const ACTION = 'mao_tk_zibll_toolkit_save_module';
    private $manager;
    private $theme;
    private $extension_failed;
    private $screen_hook = '';
    private $extra_screen_hooks = array();

    public function __construct(ModuleManager $manager, array $theme, bool $extension_failed = false)
    {
        $this->manager = $manager;
        $this->theme = $theme;
        $this->extension_failed = $extension_failed;
    }

    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_controls'), 100);
        add_action('admin_post_' . self::ACTION, array($this, 'save'));
        add_filter('plugin_action_links_' . plugin_basename(MAO_TK_ZIBLL_TOOLKIT_FILE), array($this, 'action_links'));
    }

    public function menu(): void
    {
        $this->screen_hook = (string) add_menu_page(
            __('Mao TK Zibll Toolkit', 'mao-tk-zibll-toolkit'),
            'Mao TK Toolkit',
            'manage_options',
            self::SLUG,
            array($this, 'render'),
            $this->menu_icon(),
            81
        );
    }

    private function menu_icon(): string
    {
        $path = MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/images/expand.svg';
        if (!is_readable($path)) {
            return 'dashicons-screenoptions';
        }
        $svg = file_get_contents($path);
        if (!is_string($svg) || $svg === '') {
            return 'dashicons-screenoptions';
        }
        // This local asset is normalized and reviewed at build time, not user input.
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function action_links(array $links): array
    {
        if (current_user_can('manage_options')) {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">' . esc_html__('打开工作台', 'mao-tk-zibll-toolkit') . '</a>');
        }
        return $links;
    }

    public function enqueue(string $hook_suffix): void
    {
        if (($this->screen_hook === '' || ($hook_suffix !== $this->screen_hook && !in_array($hook_suffix, $this->extra_screen_hooks, true))) || !current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_style('mao-tk-zibll-toolkit-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/admin.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_add_inline_style('mao-tk-zibll-toolkit-admin', $this->scheme_css());
        wp_enqueue_script('mao-tk-zibll-toolkit-admin', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/js/admin.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    public function enqueue_controls(string $hook_suffix): void
    {
        if (($this->screen_hook === '' || ($hook_suffix !== $this->screen_hook && !in_array($hook_suffix, $this->extra_screen_hooks, true))) || !current_user_can('manage_options')) {
            return;
        }
        // Last layer of the admin cascade, after the registered module-specific styles.
        wp_enqueue_style('mao-tk-toolkit-admin-controls', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/admin-controls.css', array('mao-tk-zibll-toolkit-admin'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
    }

    public function allow_screen(string $hook): void
    {
        if ($hook !== '' && !in_array($hook, $this->extra_screen_hooks, true)) { $this->extra_screen_hooks[] = $hook; }
    }

    private function scheme_css(): string
    {
        global $_wp_admin_css_colors;
        $scheme = get_user_option('admin_color');
        if (!is_string($scheme) || !isset($_wp_admin_css_colors[$scheme])) {
            return '';
        }
        $colors = $_wp_admin_css_colors[$scheme]->colors ?? array();
        $accent = is_array($colors) && isset($colors[2]) ? sanitize_hex_color($colors[2]) : '';
        if (!$accent) {
            return '';
        }
        // The core CSS variable takes precedence; registered preview color is fallback only.
        return '.mao-tk-toolkit{--mao-tk-scheme-accent:' . $accent . ';}';
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('你没有修改模块设置的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die(esc_html__('请通过工作台按钮提交更改。', 'mao-tk-zibll-toolkit'), '', array('response' => 405));
        }
        check_admin_referer(self::ACTION);
        if (isset($_POST['operation']) && $_POST['operation'] === 'article-note-settings') { ArticleNote\AdminPage::save($this->manager); }
        $id = isset($_POST['module_id']) && is_string($_POST['module_id']) ? wp_unslash($_POST['module_id']) : '';
        $enabled = isset($_POST['enabled']) && is_string($_POST['enabled']) ? wp_unslash($_POST['enabled']) : '';
        if (!preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) || !$this->manager->has($id) || !in_array($enabled, array('0', '1'), true)) {
            wp_die(esc_html__('模块或目标状态无效，请返回工作台重试。', 'mao-tk-zibll-toolkit'), '', array('response' => 400));
        }
        if ($id === Maintenance\Settings::MODULE && $enabled === '1' && (Maintenance\Runtime::conflict() || !(new Maintenance\Settings())->read()['valid'])) {
            wp_die('维护页配置异常或旧维护模式仍在运行，未开启维护。', '', array('response' => 400));
        }
        $saved = $this->manager->set_enabled($id, $enabled === '1');
        $return_page = isset($_POST['return_page']) && is_string($_POST['return_page']) && in_array($_POST['return_page'], array(TopInfo\AdminPage::SLUG, Freshness\AdminPage::SLUG, ArticleUI\AdminPage::SLUG, ArticleNote\AdminPage::SLUG, Showcase\AdminPage::SLUG, PrivateLinks\AdminPage::SLUG, Maintenance\AdminPage::SLUG, ProfilePresets\AdminPage::SLUG, OrderQuery\AdminPage::SLUG, NotFound\AdminPage::SLUG), true)
            ? $_POST['return_page'] : self::SLUG;
        wp_safe_redirect(add_query_arg(
            array('page' => $return_page, 'mao_tk_notice' => $saved ? 'saved' : 'save-failed'),
            admin_url('admin.php')
        ));
        exit;
    }

    /** Progressive enhancement: all settings remain visible when JavaScript is unavailable. */
    public static function disclosure_start(string $id, string $title, string $summary = '', bool $open = false, string $group = 'settings'): void
    {
        echo '<section class="mao-tk-disclosure" data-mao-tk-disclosure data-accordion-group="' . esc_attr($group) . '" data-initial-open="' . ($open ? 'true' : 'false') . '">';
        echo '<h3 class="mao-tk-disclosure__heading"><button type="button" class="mao-tk-disclosure__trigger" id="' . esc_attr($id . '-trigger') . '" aria-expanded="true" aria-controls="' . esc_attr($id . '-panel') . '">';
        echo '<span class="mao-tk-disclosure__label"><span>' . esc_html($title) . '</span>';
        if ($summary !== '') { echo '<span class="mao-tk-disclosure__summary">' . esc_html($summary) . '</span>'; }
        echo '</span><svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button></h3>';
        echo '<div class="mao-tk-disclosure__panel" id="' . esc_attr($id . '-panel') . '" role="region" aria-labelledby="' . esc_attr($id . '-trigger') . '"><div class="mao-tk-disclosure__inner">';
    }

    public static function disclosure_end(): void
    {
        echo '</div></div></section>';
    }

    private function state(string $status): array
    {
        $states = array(
            'loaded' => array('success', __('运行中', 'mao-tk-zibll-toolkit'), __('模块已在本次请求中加载。', 'mao-tk-zibll-toolkit')),
            'disabled' => array('neutral', __('未启用', 'mao-tk-zibll-toolkit'), __('默认关闭，可按需启用。', 'mao-tk-zibll-toolkit')),
            'blocked' => array('warning', __('等待主题', 'mao-tk-zibll-toolkit'), __('开关已开启，识别到 Zibll 后才会加载。', 'mao-tk-zibll-toolkit')),
            'error' => array('error', __('加载失败', 'mao-tk-zibll-toolkit'), __('本次加载发生异常，你可以先关闭此模块。', 'mao-tk-zibll-toolkit')),
            'invalid' => array('error', __('配置异常', 'mao-tk-zibll-toolkit'), __('已暂停加载，原有配置未被覆盖。', 'mao-tk-zibll-toolkit')),
            'waiting-provider' => array('warning', __('等待区块', 'mao-tk-zibll-toolkit'), __('外观开关已开启，但尚无可安全适配的提示框提供者，请查看正文提示框设置。', 'mao-tk-zibll-toolkit')),
        );
        return $states[$status] ?? $states['error'];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('你没有访问此页面的权限。', 'mao-tk-zibll-toolkit'), '', array('response' => 403));
        }
        $theme = $this->theme;
        $modules = $this->manager->all();
        foreach ($modules as &$module) { if ($module['id'] === 'article-note' && $module['status'] === 'loaded' && !ArticleNote\Integration::instance()->usable()) { $module['status'] = 'waiting-provider'; } } unset($module);
        $loaded = count(array_filter($modules, static function (array $module): bool {
            return $module['status'] === 'loaded';
        }));
        $storage_valid = $this->manager->storage_is_valid();
        $registration_failed = $this->extension_failed || $this->manager->registration_errors() > 0;
        $notice = isset($_GET['mao_tk_notice']) && is_string($_GET['mao_tk_notice']) ? wp_unslash($_GET['mao_tk_notice']) : '';
        $icon_exists = is_readable(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/images/maotk-icon.svg');
        global $wp_version;
        require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/dashboard.php';
    }
}
