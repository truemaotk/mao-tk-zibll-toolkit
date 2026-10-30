<?php
namespace MaoTK\ZibllToolkit\ArticleUI;

defined('ABSPATH') || exit;

/** Presentation only: never changes post_content, block serialization or post metadata. */
final class Content
{
    public static function inspect(string $html): array
    {
        $result = array('roots' => 0, 'components' => array(), 'embedded' => array(), 'legacy_cards' => 0);
        if (!class_exists('WP_HTML_Tag_Processor')) { return $result; }
        $tags = new \WP_HTML_Tag_Processor($html);
        while ($tags->next_tag()) {
            $tag = $tags->get_tag();
            $classes = preg_split('/\s+/', (string) $tags->get_attribute('class'), -1, PREG_SPLIT_NO_EMPTY);
            if (in_array($tag, array('DIV', 'SECTION', 'ARTICLE'), true) && in_array('maotk-ui-kit', $classes, true)) { $result['roots']++; }
            foreach (array('mtk-code-wrap' => 'code', 'mtk-native-tabs' => 'tabs', 'mtk-native-postsbox' => 'postsbox', 'mtk-article-reading' => 'reading') as $class => $key) {
                if (in_array($class, $classes, true)) { $result['components'][$key] = true; }
            }
            if ($tag === 'A') {
                $href = $tags->get_attribute('href');
                if (is_string($href) && preg_match('~^(?:https?://|/[^/])~i', $href)) { $result['components']['link-preview'] = true; }
            }
            // Conservative migration gate: an inline bundle cannot safely coexist with new handlers.
            if (in_array($tag, array('STYLE', 'SCRIPT'), true)) {
                $result['embedded'][] = array('tag' => $tag, 'src' => (string) $tags->get_attribute('src'));
            }
            if ($tags->get_attribute('data-mtk-link-preview') !== null || $tags->get_attribute('data-mtk-link-ready') === '1') { $result['legacy_cards']++; }
        }
        if (!$result['roots']) { $result['components'] = array(); }
        return $result;
    }

    public static function annotate(string $html, array $active): string
    {
        if (!class_exists('WP_HTML_Tag_Processor')) { return $html; }
        $tags = new \WP_HTML_Tag_Processor($html);
        while ($tags->next_tag(array('class_name' => 'maotk-ui-kit'))) {
            if (!in_array($tags->get_tag(), array('DIV', 'SECTION', 'ARTICLE'), true)) { continue; }
            foreach (Catalog::all() as $key => $component) {
                if (in_array($key, $active, true)) { $tags->set_attribute(Catalog::attribute($key), '1'); }
                else { $tags->remove_attribute(Catalog::attribute($key)); }
            }
        }
        return $tags->get_updated_html();
    }
}
