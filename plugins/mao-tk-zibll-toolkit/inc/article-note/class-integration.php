<?php
namespace MaoTK\ZibllToolkit\ArticleNote;

defined('ABSPATH') || exit;

final class Integration
{
    public const BLOCK = 'maotk/note';
    public const BASE = 'mao-tk-toolkit-note-base';
    public const VISUAL = 'mao-tk-toolkit-note-visual';
    public const EDITOR = 'mao-tk-toolkit-note-editor';
    public const PROVIDER = 'mao-tk-toolkit-note-provider';
    private static $instance;
    private $registered = false;
    private $enhance = false;
    private $taking = false;
    private $adapted = false;
    private $status = 'pending';
    public static function instance(): self { if (!self::$instance) { self::$instance = new self(); } return self::$instance; }
    public static function supported(): bool
    {
        global $wp_version;
        return is_string($wp_version) && version_compare($wp_version, '6.3', '>=') && class_exists('WP_HTML_Tag_Processor') && class_exists('WP_Block_Type_Registry') && function_exists('register_block_type');
    }
    public function register(): void
    {
        if ($this->registered) { return; } $this->registered = true;
        add_filter('register_block_type_args', array($this, 'adapt_args'), 10, 2);
        add_action('init', array($this, 'assets'), 0);
        add_action('init', array($this, 'provider'), 99);
        add_action('wp_enqueue_scripts', array($this, 'front_assets'), 30);
        add_action('enqueue_block_editor_assets', array($this, 'editor'));
        add_action('enqueue_block_assets', array($this, 'editor_styles'));
        add_filter('render_block_maotk/note', array($this, 'render'), 10, 3);
    }
    public function enable(): void { $this->enhance = true; }
    private function legacy(): bool { return function_exists('maotk_note_block_register') && has_action('init', 'maotk_note_block_register') !== false; }
    private function block() { return class_exists('WP_Block_Type_Registry') ? \WP_Block_Type_Registry::get_instance()->get_registered(self::BLOCK) : null; }
    public function status(): string { return $this->status; }
    public function usable(): bool { return in_array($this->status, array('toolkit', 'legacy'), true); }

    public function assets(): void
    {
        if (!self::supported()) { return; }
        $url = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/'; $version = MAO_TK_ZIBLL_TOOLKIT_VERSION;
        wp_register_style(self::BASE, $url . 'base.css', array(), $version);
        wp_register_style('mao-tk-toolkit-note-editor-base', $url . 'editor-base.css', array(self::BASE), $version);
        wp_register_script('mao-tk-toolkit-note-model', $url . 'model.js', array(), $version, true);
        wp_register_script(self::EDITOR, $url . 'editor.js', array('mao-tk-toolkit-note-model', 'wp-hooks', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'), $version, true);
        wp_set_script_translations(self::EDITOR, 'mao-tk-zibll-toolkit', MAO_TK_ZIBLL_TOOLKIT_DIR . 'languages');
        wp_register_script(self::PROVIDER, $url . 'provider.js', array(self::EDITOR), $version, true);
    }
    public function adapt_args(array $args, string $name): array
    {
        if ($name !== self::BLOCK || !self::supported() || (!$this->taking && !$this->legacy())) { return $args; }
        $attrs = $args['attributes'] ?? array();
        foreach (array('type' => 'string', 'badge' => 'string', 'title' => 'string', 'showBadge' => 'boolean', 'showTitle' => 'boolean') as $key => $type) {
            if (($attrs[$key]['type'] ?? null) !== $type) { return $args; }
        }
        if (($args['api_version'] ?? null) !== 3) { return $args; }
        $args['attributes'][Settings::OVERRIDE] = array('type' => 'object');
        $handles = $args['editor_script_handles'] ?? array();
        if (!is_array($handles) || !$handles) { return $args; }
        // These are the actual metadata-resolved handles, not guessed legacy script names.
        $scripts = wp_scripts();
        foreach ($handles as $handle) {
            if (is_string($handle) && $handle !== self::EDITOR && isset($scripts->registered[$handle]) && !in_array(self::EDITOR, $scripts->registered[$handle]->deps, true)) { $scripts->registered[$handle]->deps[] = self::EDITOR; }
        }
        $this->adapted = true;
        return $args;
    }
    public function provider(): void
    {
        if (!self::supported()) { $this->status = 'unsupported'; return; }
        $block = $this->block();
        if ($block) { $this->status = $this->legacy() && $this->adapted ? 'legacy' : 'unknown'; return; }
        $state = (new Settings())->read();
        if ($state['provider'] !== 'toolkit') { $this->status = 'missing'; return; }
        if ($this->legacy()) { $this->status = 'legacy-failed'; return; }
        $this->taking = true;
        try { $registered = register_block_type(MAO_TK_ZIBLL_TOOLKIT_DIR . 'blocks/note'); }
        catch (\Throwable $error) { $registered = false; }
        finally { $this->taking = false; }
        $this->status = $registered && $this->adapted ? 'toolkit' : 'unknown';
    }
    private function scope(): bool
    {
        if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || !$this->usable()) { return false; }
        if (!is_singular()) { return false; } $post = get_post(get_queried_object_id());
        return $post && in_array($post->post_type, array('post', 'page'), true) && $post->post_password === '';
    }
    private function visual_scope(string $type): bool
    {
        $state = (new Settings())->read();
        return $this->enhance && $state['valid'] && get_template() === 'zibll' && ($type === 'post' || ($type === 'page' && $state['value']['include_pages']));
    }
    public function visual_assets(): void
    {
        $block = $this->block(); $deps = $block && is_array($block->style_handles ?? null) ? $block->style_handles : array();
        if ($this->status === 'toolkit') { $deps[] = self::BASE; }
        wp_enqueue_style(self::VISUAL, MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/note/note.css', array_values(array_unique($deps)), MAO_TK_ZIBLL_TOOLKIT_VERSION);
    }
    public function front_assets(): void
    {
        if (!$this->scope() || !function_exists('has_block') || !has_block(self::BLOCK, get_post(get_queried_object_id()))) { return; }
        if ($this->status === 'toolkit') { wp_enqueue_style(self::BASE); }
        if ($this->visual_scope((string) get_post(get_queried_object_id())->post_type)) { $this->visual_assets(); }
    }
    public function render(string $html, array $block, $instance = null): string
    {
        $current = get_post();
        $post_id = is_object($instance) && isset($instance->context['postId'])
            ? (int) $instance->context['postId'] : (int) ($current->ID ?? 0);
        if (!$this->scope() || $post_id !== (int) get_queried_object_id()) { return $html; }
        if ($this->status === 'toolkit') { wp_enqueue_style(self::BASE); }
        if (!$this->visual_scope((string) get_post(get_queried_object_id())->post_type)) { return $html; }
        $this->visual_assets(); $settings = (new Settings())->read()['value']; $attrs = $block['attrs'] ?? array();
        $type = isset($attrs['type']) && is_string($attrs['type']) ? $attrs['type'] : 'info';
        return Visual::apply($html, Settings::resolve($type, $settings, $attrs[Settings::OVERRIDE] ?? array()));
    }
    public function editor_styles(): void
    {
        if (!is_admin() || !$this->usable()) { return; }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) { return; }
        if ($this->status === 'toolkit') { wp_enqueue_style('mao-tk-toolkit-note-editor-base'); }
        if ($this->visual_scope((string) $screen->post_type)) { $this->visual_assets(); }
    }
    public function editor(): void
    {
        if (!$this->usable()) { return; }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) { return; }
        $state = (new Settings())->read(); $payload = Settings::payload($state['value']);
        $payload['enabled'] = $this->visual_scope((string) $screen->post_type); $payload['provider'] = $this->status;
        wp_enqueue_script(self::EDITOR);
        wp_add_inline_script(self::EDITOR, 'window.MaoTKNoteEditor=' . wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
}
