<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class Editor
{
    public function register(): void
    {
        // Control-plane functionality remains available even while front-end output is off.
        add_action('add_meta_boxes_post', array($this, 'add_box'));
        add_action('save_post_post', array($this, 'save'), 10, 1);
    }

    public function add_box(): void
    {
        add_meta_box('mao-tk-toolkit-top-info-property', '文章顶部信息 · 文章属性', array($this, 'render'), 'post', 'side', 'default');
    }

    public function render($post): void
    {
        if (!current_user_can('edit_post', (int) $post->ID)) { return; }
        $current = (new ArticleData())->property((int) $post->ID);
        $labels = (new Settings())->read()['value']['properties'];
        if ($current !== '' && !in_array($current, $labels, true)) { $labels[] = $current; }
        wp_nonce_field('mao_tk_top_info_property_' . (int) $post->ID, 'mao_tk_top_info_property_nonce');
        echo '<p>模块关闭时仍保留属性；前台展示由 Toolkit 开关控制。</p>';
        foreach (array_merge(array(''), $labels) as $label) {
            echo '<p><label><input type="radio" name="mao_tk_top_info_property" value="' . esc_attr($label) . '" ' . checked($current, $label, false) . '> ' . esc_html($label === '' ? '不显示属性' : $label) . '</label></p>';
        }
    }

    public function save(int $post_id): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || get_post_type($post_id) !== 'post') { return; }
        $nonce = $_POST['mao_tk_top_info_property_nonce'] ?? null;
        $value = $_POST['mao_tk_top_info_property'] ?? null;
        if (!current_user_can('edit_post', $post_id) || !is_string($nonce) || !is_string($value)
            || !wp_verify_nonce(wp_unslash($nonce), 'mao_tk_top_info_property_' . $post_id)) { return; }
        $value = sanitize_text_field(wp_unslash($value));
        $allowed = (new Settings())->read()['value']['properties'];
        $allowed[] = '';
        $allowed[] = (new ArticleData())->property($post_id);
        if (in_array($value, $allowed, true)) {
            // An explicit empty new value hides the old badge without deleting the old meta.
            update_post_meta($post_id, ArticleData::PROPERTY_META, $value);
        }
    }
}
