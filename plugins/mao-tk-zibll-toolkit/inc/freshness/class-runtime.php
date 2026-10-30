<?php
namespace MaoTK\ZibllToolkit\Freshness;

defined('ABSPATH') || exit;

final class Runtime
{
    private $rendered = false;

    public static function conflict(): bool
    {
        return has_action('zib_posts_content_before', 'article_time_update') !== false;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'), 20);
        add_action('zib_posts_content_before', array($this, 'output'), 10, 0);
        add_filter('the_content', array($this, 'filter_content'), 20);
    }

    private function eligible(): bool
    {
        $post = get_post();
        return !is_admin() && is_singular('post') && !is_feed() && !self::conflict()
            && is_object($post) && (int) $post->ID === (int) get_queried_object_id();
    }

    public function notice($post, ?int $now = null): string
    {
        if (!is_object($post) || $post->post_type !== 'post' || $post->post_status !== 'publish' || $post->post_password !== '') { return ''; }
        $state = (new Settings())->read();
        if (!$state['valid']) { return ''; }
        $modified = get_post_timestamp($post, 'modified');
        if (!is_int($modified) || $modified <= 0 || ($now ?? time()) - $modified <= $state['value']['days'] * DAY_IN_SECONDS) { return ''; }
        return self::markup($state['value'], $modified);
    }

    public static function markup(array $settings, int $modified): string
    {
        $date = wp_date('Y-m-d H:i:s', $modified, wp_timezone());
        $html = '<div class="mao-tk-article-freshness" role="note"><strong><span class="mao-tk-article-freshness__icon" aria-hidden="true"></span>温馨提示：</strong>本文最后更新于 <time datetime="' . esc_attr(gmdate('c', $modified)) . '">' . esc_html($date) . '</time>。';
        $html .= '<span>' . esc_html($settings['message']) . '</span><span class="mao-tk-article-freshness__links"><a href="#respond">留言反馈</a>';
        if ($settings['contact_url'] !== '') { $html .= '<span>或</span><a href="' . esc_url($settings['contact_url']) . '" target="_blank" rel="noopener noreferrer">联系站长</a>'; }
        return $html . '</span></div>';
    }

    public function enqueue(): void
    {
        if ($this->eligible() && $this->notice(get_post()) !== '') {
            wp_enqueue_style('mao-tk-toolkit-article-freshness', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/article-freshness.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        }
    }

    public function output(): void
    {
        if ($this->rendered || !$this->eligible()) { return; }
        $html = $this->notice(get_post());
        if ($html !== '') { $this->rendered = true; echo $html; }
    }

    public function filter_content(string $content): string
    {
        if ($this->rendered || !$this->eligible() || !in_the_loop() || !is_main_query()) { return $content; }
        $html = $this->notice(get_post());
        if ($html !== '') { $this->rendered = true; return $html . $content; }
        return $content;
    }
}
