<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class Runtime
{
    private $rendered = false;

    public static function conflict(): bool
    {
        return function_exists('MaoTK\\TopInfo\\maotk_top_info_init') || function_exists('moxing_init') || function_exists('moxing_top_info_new');
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'), 20);
        add_action('zib_single_before', array($this, 'output'), 5, 0);
        add_action('wp_footer', array($this, 'footer'), 1, 0);
        add_action('save_post_post', array(new ArticleData(), 'refresh_stats'), 20, 3);
    }

    public function eligible(): bool
    {
        if (is_admin() || !is_singular('post') || is_feed() || self::conflict()) { return false; }
        $post = get_post();
        return $post && $post->post_type === 'post' && $post->post_status === 'publish' && $post->post_password === '';
    }

    public function enqueue(): void
    {
        if (!$this->eligible()) { return; }
        $url = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/top-info/';
        wp_enqueue_style('mao-tk-toolkit-top-info-icons', $url . 'iconfont/iconfont.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_style('mao-tk-toolkit-top-info', $url . 'top-info.css', array('mao-tk-toolkit-top-info-icons'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        // Run placement during parsing, before the article's first rendered frame.
        wp_enqueue_script('mao-tk-toolkit-top-info-reveal', $url . 'reveal.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, false);
        wp_enqueue_script('mao-tk-toolkit-top-info', $url . 'top-info.js', array('mao-tk-toolkit-top-info-reveal'), MAO_TK_ZIBLL_TOOLKIT_VERSION, false);
    }

    public function output(): void
    {
        $this->render('primary');
    }

    public function footer(): void
    {
        $this->render('footer');
    }

    private function render(string $origin): void
    {
        if ($this->rendered || !$this->eligible()) { return; }
        $state = (new Settings())->read();
        if (!$state['valid']) { return; }
        try {
            $data = (new ArticleData())->collect(get_post());
            $html = (new Renderer())->render($data, $state['value']);
        } catch (\Throwable $error) {
            return; // Never hide the original title when rendering fails.
        }
        if ($html === '') { return; }
        $this->rendered = true;
        // Hidden until a recognized theme container exists; no-JS keeps the native article intact.
        echo '<div id="maotk-top-info-fallback" hidden data-maotk-origin="' . esc_attr($origin) . '">' . $html . '</div>';
    }
}
