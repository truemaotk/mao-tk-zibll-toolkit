<?php
namespace MaoTK\ZibllToolkit\ArticleUI;

defined('ABSPATH') || exit;

final class Runtime
{
    private static $instance;
    private $enabled = array();
    private $registered = false;
    private $prepared = array();
    private $template_printed = false;

    public static function instance(): self
    {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    public function enable(string $key): void
    {
        if (!isset(Catalog::all()[$key])) { return; }
        $this->enabled[$key] = true;
        if ($this->registered) { return; }
        $this->registered = true;
        add_action('wp_enqueue_scripts', array($this, 'enqueue'), 30);
        add_filter('the_content', array($this, 'content'), 99);
        add_action('wp_footer', array($this, 'template'), 5);
    }

    private function eligible(): bool
    {
        if (is_admin() || is_feed() || !is_singular('post') || get_template() !== 'zibll' || !class_exists('WP_HTML_Tag_Processor') || (defined('REST_REQUEST') && REST_REQUEST)) { return false; }
        $post = get_post(get_queried_object_id());
        return $post && $post->post_type === 'post' && $post->post_password === '' &&
            ($post->post_status === 'publish' || (function_exists('is_preview') && is_preview() && current_user_can('edit_post', $post->ID)));
    }

    public function enqueue(): void
    {
        if (!$this->eligible()) { return; }
        // The global queried post carries WordPress's authorized preview content;
        // fetching its ID again can return the old published body from the cache.
        $post = get_post();
        if (!$post || (int) $post->ID !== (int) get_queried_object_id()) { $post = get_post(get_queried_object_id()); }
        $inspection = Content::inspect((string) $post->post_content);
        if ($inspection['embedded']) { return; }
        $catalog = Catalog::all();
        foreach ($this->enabled as $key => $enabled) {
            if (empty($inspection['components'][$key]) || ($key === 'link-preview' && $inspection['legacy_cards'])) { continue; }
            $this->prepared[$key] = true;
            wp_enqueue_style('mao-tk-toolkit-article-ui-' . $key, MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/' . $catalog[$key]['file'], array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        }
        if (isset($this->prepared['link-preview']) || isset($this->prepared['tabs'])) {
            // Theme scripts keep their own handles/order. No guessed theme dependency or copied loader.
            wp_enqueue_script('mao-tk-toolkit-article-ui', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/article-ui.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        }
        if (isset($this->prepared['resources'])) {
            wp_enqueue_style('mao-tk-toolkit-article-ui-resource-adapters', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/resources/adapters.css', array('mao-tk-toolkit-article-ui-resources'), MAO_TK_ZIBLL_TOOLKIT_VERSION);
            if (isset($this->prepared['link-preview'])) {
                wp_enqueue_script('mao-tk-toolkit-article-resources', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/resources/link-bridge.js', array('mao-tk-toolkit-article-ui'), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
            }
        }
        if (isset($this->prepared['reading'])) {
            wp_enqueue_script('mao-tk-toolkit-article-reading', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/article-ui/reading/reading.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
        }
    }

    public function content(string $html): string
    {
        // Zibll can render the queried article without entering the WP Loop.
        // Match the actual post instead of mistaking that render for a secondary query.
        $current = get_post();
        if (!$this->eligible() || !$current || (int) $current->ID !== (int) get_queried_object_id()) { return $html; }
        // A foreign filter may have added legacy assets after the head scan. Do not enhance it.
        $inspection = Content::inspect($html);
        $active = $inspection['embedded'] ? array() : array_keys($this->prepared);
        if ($inspection['legacy_cards']) { $active = array_values(array_diff($active, array('link-preview'))); }
        return Content::annotate($html, $active);
    }

    public function template(): void
    {
        if ($this->template_printed || empty($this->prepared['link-preview']) || !$this->eligible()) { return; }
        $this->template_printed = true;
        $path = MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/article-ui/link-preview/card.html';
        if (!is_readable($path)) { return; }
        // Reviewed static local markup; no request-built paths or interpolated user content.
        echo '<template id="mao-tk-ui-link-template">' . file_get_contents($path) . '</template>';
    }
}
