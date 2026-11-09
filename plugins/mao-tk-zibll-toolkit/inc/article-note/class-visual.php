<?php
namespace MaoTK\ZibllToolkit\ArticleNote;

defined('ABSPATH') || exit;

final class Visual
{
    public static function apply(string $html, array $value): string
    {
        if (!class_exists('WP_HTML_Tag_Processor')) { return $html; }
        $tags = new \WP_HTML_Tag_Processor($html);
        if (!$tags->next_tag(array('tag_name' => 'ASIDE', 'class_name' => 'maotk-note-clean'))) { return $html; }
        $classes = preg_split('/\s+/', (string) $tags->get_attribute('class'));
        if (!in_array('wp-block-maotk-note', $classes, true)) { return $html; }
        $icons = Icons::all(); $icon = $icons[$value['iconKey']]['url'] ?? '';
        $style = $tags->get_attribute('style'); $style = is_string($style) ? $style : '';
        $style = preg_replace('/(^|;)\s*--maotk-note-(?:color-light|color-dark|icon-size|icon)\s*:[^;]*(?=;|$)/i', '$1', $style);
        $style = rtrim($style, '; ') . ';--maotk-note-color-light:' . $value['light'] . ';--maotk-note-color-dark:' . $value['dark'] . ';--maotk-note-icon-size:' . $value['iconSize'] . 'px;--maotk-note-icon:url("' . $icon . '");';
        $tags->set_attribute('style', ltrim($style, ';'));
        $tags->set_attribute('data-mao-tk-note-visual', '1');
        $tags->set_attribute('data-maotk-note-badge-position', $value['badgePosition']);
        $tags->set_attribute('data-maotk-note-icon-position', $value['iconPosition']);
        $tags->set_attribute('data-maotk-note-icon', $value['showIcon'] ? 'show' : 'none');
        $tags->set_attribute('data-maotk-note-title-color', $value['titleColor']);
        return $tags->get_updated_html();
    }

    public static function sample(string $type): string
    {
        $label = translate(Settings::types()[$type] ?? '说明', 'mao-tk-zibll-toolkit');
        return '<aside class="wp-block-maotk-note maotk-note-clean maotk-note-' . esc_attr($type) . ' has-maotk-note-badge has-maotk-note-title" data-maotk-note-type="' . esc_attr($type) . '" aria-label="' . esc_attr($label) . '">'
            . '<div class="maotk-note-badge"><span>' . esc_html($label) . '</span></div>'
            . '<div class="maotk-note-title">' . esc_html__('适合哪些使用场景', 'mao-tk-zibll-toolkit') . '</div>'
            . '<div class="maotk-note-content"><p>' . esc_html__('用于正文中的说明和提醒。文字、段落和列表继续保留，内部标题不会加入文章目录。', 'mao-tk-zibll-toolkit') . '</p>'
            . '<ul><li>' . esc_html__('先检查当前设置，再保存。', 'mao-tk-zibll-toolkit') . '</li>'
            . '<li>' . esc_html__('标签、图标、标题与左侧色条使用同一类型色。', 'mao-tk-zibll-toolkit') . '</li></ul></div></aside>';
    }
}
