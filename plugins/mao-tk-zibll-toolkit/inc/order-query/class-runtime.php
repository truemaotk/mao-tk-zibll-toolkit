<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
defined('ABSPATH') || exit;

final class Runtime
{
    public const ACTION = 'mao_tk_order_query';
    private static $enabled = false;
    public static function enable(): void { self::$enabled = true; }
    public static function enabled(): bool { return self::$enabled && get_template() === 'zibll' && (new Settings())->read()['valid']; }
    public static function register(): void
    {
        add_filter('theme_page_templates', array(__CLASS__, 'templates'));
        add_filter('template_include', array(__CLASS__, 'template'), 90);
        add_action('template_redirect', array(__CLASS__, 'cache_headers'), 0);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 25);
        add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'ajax'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array(__CLASS__, 'ajax'));
    }
    public static function templates(array $templates): array
    {
        if (get_template() === 'zibll') { $templates[Settings::TEMPLATE] = 'MaoTK 订单查询'; }
        return $templates;
    }
    public static function selected(int $id): bool { return $id > 0 && get_post_type($id) === 'page' && get_page_template_slug($id) === Settings::TEMPLATE; }
    public static function template(string $template): string
    {
        return get_template() === 'zibll' && is_page() && self::selected((int) get_queried_object_id()) ? MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/order-query/template.php' : $template;
    }
    public static function cache_headers(): void
    {
        if (get_template() !== 'zibll' || !is_page() || !self::selected((int) get_queried_object_id())) { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        if (!headers_sent()) { header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'); header('Referrer-Policy: same-origin'); }
    }
    public static function enqueue(): void
    {
        if (!is_page() || !self::selected((int) get_queried_object_id()) || get_template() !== 'zibll') { return; }
        wp_enqueue_style('mao-tk-order-query', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/order-query/view.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        if (self::enabled() && Orders::available() && !post_password_required()) { wp_enqueue_script('mao-tk-order-query', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/order-query/view.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true); }
    }
    public static function ajax(): void
    {
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_send_json_error(array('message' => '请通过页面查询。'), 405); }
        $id = isset($_POST['page_id']) && is_string($_POST['page_id']) && ctype_digit($_POST['page_id']) ? (int) $_POST['page_id'] : 0;
        $page = get_post($id);
        if (!self::enabled() || !Orders::available() || !$page || !self::selected($id) || post_password_required($page) || ($page->post_status !== 'publish' && !current_user_can('read_post', $id))) { wp_send_json_error(array('message' => '查询入口暂不可用。'), 403); }
        $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, self::ACTION . '_' . $id)) { wp_send_json_error(array('message' => '页面已过期，请刷新后重试。'), 403); }
        $settings = (new Settings())->read()['value'];
        $mode = $_POST['mode'] ?? 'search';
        if (!is_string($mode) || !in_array($mode, array('search', 'history', 'clear'), true)) { wp_send_json_error(array('message' => '查询方式无效。'), 400); }
        if ($mode !== 'search' && (!$settings['history_enabled'] || !get_current_user_id())) { wp_send_json_error(array('message' => '请先登录订单账号。'), 403); }
        // Store a short-lived salted key, never the keyword, contact details, or plain IP.
        $identity = get_current_user_id() ? 'u' . get_current_user_id() : 'ip' . (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'mao_tk_oq_' . substr(hash_hmac('sha256', $identity, wp_salt('nonce')), 0, 32);
        $count = (int) get_transient($key);
        if ($count >= 20) { wp_send_json_error(array('message' => '查询较频繁，请稍等一分钟后重试。'), 429); }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        try {
            if ($mode === 'search') {
                $keyword = Orders::keyword(isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : '');
                $raw_page = $_POST['result_page'] ?? '1';
                if (!is_string($raw_page) || !ctype_digit($raw_page)) { throw new \InvalidArgumentException('页码无效。'); }
                $result = Orders::search($keyword, (int) $raw_page);
                if ($settings['history_enabled']) { Orders::remember($result['orders']); }
            } else { $result = array('orders' => Orders::history($mode === 'clear'), 'has_next' => false, 'page' => 1); }
            wp_send_json_success(array('html' => Renderer::results($result, $mode), 'has_next' => $result['has_next'], 'page' => $result['page'], 'message' => $mode === 'clear' ? '最近查询记录已清空。订单未删除。' : ($result['orders'] ? '查询完成。' : '没有找到可查看的订单。')));
        } catch (\InvalidArgumentException $e) { wp_send_json_error(array('message' => $e->getMessage()), 400); }
        catch (\Throwable $e) { wp_send_json_error(array('message' => '订单查询暂不可用，请稍后重试或联系网站客服。'), 503); }
    }
}
