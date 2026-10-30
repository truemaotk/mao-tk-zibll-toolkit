<?php
namespace MaoTK\ZibllToolkit\Showcase;

defined('ABSPATH') || exit;

final class Renderer
{
    public static function icon(string $name): string
    {
        if (!preg_match('/^[a-z0-9-]{1,40}$/D', $name) || !is_file(MAO_TK_ZIBLL_TOOLKIT_DIR . 'assets/showcase/icons/' . $name . '.svg')) { $name = 'circle-help'; }
        $url = MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/showcase/icons/' . $name . '.svg';
        return '<i class="icon" aria-hidden="true" style="--icon:url(&quot;' . esc_url($url) . '&quot;)"></i>';
    }

    private static function button_icon(array $settings, bool $two): string
    {
        $prefix = $two && !$settings['syncButtonIcons'] ? 'buttonTwo' : 'buttonOne';
        $url = Settings::url($settings[$prefix . 'CustomIcon']);
        if ($url !== '') { return '<img class="custom-button-icon" src="' . esc_url($url) . '" alt="" aria-hidden="true" draggable="false">'; }
        return self::icon($settings[$prefix . 'Icon']);
    }

    private static function button(array $card, array $settings, bool $two): string
    {
        if (!$card[$two ? 'showButtonTwo' : 'showButtonOne']) { return ''; }
        $label = $card[$two ? 'buttonTwoText' : 'buttonOneText']; if ($label === '') { return ''; }
        $url = Settings::url($two ? $card['buttonTwoUrl'] : ($card['buttonOneUrl'] ?: $card['siteUrl']));
        $class = 'case-button ' . ($two ? 'secondary' : 'primary');
        $inner = self::button_icon($settings, $two) . '<span>' . esc_html($label) . '</span>';
        if (!$url) { return '<span class="' . $class . '" role="link" aria-disabled="true" title="请先填写按钮链接">' . $inner . '</span>'; }
        $rel = 'noopener noreferrer' . ($card[$two ? 'buttonTwoNofollow' : 'nofollow'] ? ' nofollow' : '');
        return '<a class="' . $class . '" data-link-preview="off" href="' . esc_url($url) . '"' . ($card['newTab'] ? ' target="_blank"' : '') . ' rel="' . esc_attr($rel) . '">' . $inner . '</a>';
    }

    public static function fallback(bool $error = false): string
    {
        return '<div class="shot-fallback">' . self::icon('image') . '<span>' . ($error ? '图片暂时无法加载' : '还没有选择预览图片') . '</span><small>' . ($error ? '案例链接仍可正常打开' : '添加网站截图后，在这里预览') . '</small></div>';
    }

    public static function render(array $value, string $category = '', array $options = array()): string
    {
        $s = array_replace(Settings::defaults()['settings'], $value['settings']);
        $cards = isset($options['cards']) && is_array($options['cards']) ? $options['cards'] : Settings::cards($value, $category);
        $theme = $s['themeMode'] === 'dark' ? 'dark' : 'light';
        $style = '--columns:' . $s['columns'] . ';--card-radius:' . $s['cardRadius'] . 'px;--card-gap:' . $s['gap'] . 'px;--shot-h:' . $s['imageHeight'] . 'px;--shot-mobile:' . $s['imageHeightMobile'] . 'px;--card-title-size:' . $s['cardTitleSize'] . 'px;--icon-size:' . $s['iconSize'] . 'px;--button-bg:' . $s['primaryBg'] . ';--button-hover:' . $s['primaryHover'] . ';--max-width:' . $s['maxWidth'] . 'px;';
        $style .= '--mtk-showcase-padding-top:' . $s['sectionPaddingTop'] . 'px;--mtk-showcase-padding-bottom:' . $s['sectionPaddingBottom'] . 'px;';
        foreach (array('cardBgLight' => 'card-bg-light', 'cardBgDark' => 'card-bg-dark', 'borderLight' => 'border-light', 'borderDark' => 'border-dark', 'titleLight' => 'title-light', 'titleDark' => 'title-dark') as $key => $css) { $style .= '--' . $css . ':' . $s[$key] . ';'; }
        foreach (($options['legacyCss'] ?? array()) as $property => $color) { if (preg_match('/^--legacy-[a-z-]+$/D', (string) $property) && is_string($color) && sanitize_hex_color($color)) { $style .= $property . ':' . sanitize_hex_color($color) . ';'; } }
        $show_header = isset($options['showHeader']) ? (bool) $options['showHeader'] : $s['showHeader'];
        $title_style = !empty($options['legacyTitleSize']) ? ' style="font-size:' . min(72, max(12, (int) $options['legacyTitleSize'])) . 'px"' : '';
        $block_attrs = is_array($options['blockAttributes'] ?? null) ? $options['blockAttributes'] : array();
        $anchor = is_string($block_attrs['anchor'] ?? null) && preg_match('/^[^\s"<>]{1,200}$/uD', $block_attrs['anchor']) ? $block_attrs['anchor'] : '';
        $align = in_array($block_attrs['align'] ?? null, array('wide','full'), true) ? ' align' . $block_attrs['align'] : '';
        $root_class = 'mtk-showcase showcase' . (isset($options['blockAttributes']) ? ' wp-block-maotk-demo-showcase' . $align : '');
        ob_start(); ?>
        <section class="<?php echo esc_attr($root_class); ?>"<?php if ($anchor !== '') { echo ' id="' . esc_attr($anchor) . '"'; } ?> data-mao-tk-showcase data-theme="<?php echo esc_attr($theme); ?>" data-theme-mode="<?php echo esc_attr($s['themeMode']); ?>" data-width-mode="<?php echo esc_attr($s['widthMode']); ?>" data-scroll-enabled="<?php echo $s['scrollEnabled'] ? 'true' : 'false'; ?>" data-preview-speed="<?php echo esc_attr((string) $s['previewSpeed']); ?>" aria-label="<?php echo esc_attr($s['title'] ?: '案例展示'); ?>" style="<?php echo esc_attr($style); ?>">
        <?php if ($show_header) : ?><header class="showcase-heading"><div><?php if (!empty($options['eyebrow']) && is_string($options['eyebrow'])) { echo '<span class="showcase-eyebrow">' . esc_html($options['eyebrow']) . '</span>'; } ?><h2<?php echo $title_style; ?>><?php echo esc_html($s['title']); ?></h2><?php if ($s['showDescription'] && $s['description'] !== '') : ?><p><?php echo esc_html($s['description']); ?></p><?php endif; ?></div></header><?php endif; ?>
        <?php if (!$cards) : ?><div class="showcase-empty"><?php echo self::icon('layout-grid'); ?><h3>暂无案例</h3><p>这个分类暂时没有可展示的案例。</p></div><?php else : ?>
        <div class="showcase-grid">
        <?php foreach ($cards as $card) :
            $image = $card['imageId'] ? ((function_exists('wp_get_original_image_url') ? wp_get_original_image_url($card['imageId']) : wp_get_attachment_url($card['imageId'])) ?: $card['imageUrl']) : $card['imageUrl']; $image = Settings::url($image);
            $address = $card['addressText'] ?: $card['siteUrl']; $alt = $card['imageAlt'] ?: $card['name'] . ' 网站截图';
            $label = $card['category'];
            if ($label === '' && $card['categoryIds']) { foreach ($value['categories'] as $group) { if (in_array($group['id'], $card['categoryIds'], true)) { $label = $group['name']; break; } } }
            $card_style = ''; foreach (array('imageHeight' => '--shot-h', 'imageHeightMobile' => '--shot-mobile') as $key => $variable) { if (isset($card[$key]) && is_numeric($card[$key])) { $card_style .= $variable . ':' . min(720, max(160, (int) $card[$key])) . 'px;'; } }
        ?>
        <article class="case-card" data-case-id="<?php echo esc_attr($card['id']); ?>" data-scroll-enabled="<?php echo $card['scrollEnabled'] ? 'true' : 'false'; ?>"<?php if ($card_style) { echo ' style="' . esc_attr($card_style) . '"'; } ?>>
        <?php if ($s['showBrowserBar']) : ?><div class="browser-bar">
            <?php if ($s['showBrowserDots']) : ?><span class="window-dots" aria-hidden="true"><i><b>×</b></i><i><b>−</b></i><i><b>+</b></i></span><?php endif; ?>
            <?php if ($s['showBrowserAddress']) : ?><label class="browser-domain"><?php echo self::icon(stripos($card['siteUrl'], 'https:') === 0 ? 'lock-keyhole' : 'globe'); ?><input class="browser-address-input" type="text" readonly value="<?php echo esc_attr($address); ?>" placeholder="尚未填写网址" aria-label="<?php echo esc_attr($card['name'] . ' 的完整网址'); ?>" title="<?php echo esc_attr($address . ' · 点击选中完整网址'); ?>" spellcheck="false" autocomplete="off" dir="ltr"></label><?php else : ?><span class="browser-space"></span><?php endif; ?>
            <?php if ($s['showBrowserTools']) : ?><span class="browser-actions" aria-hidden="true" title="浏览器窗口装饰"><?php echo self::icon('share') . self::icon('bookmark'); ?></span><?php endif; ?>
        </div><?php endif; ?>
        <div class="case-media">
        <?php if ($image) : ?><button type="button" class="case-shot" data-shot="<?php echo esc_attr($card['id']); ?>" aria-label="<?php echo esc_attr('预览 ' . $card['name'] . ' 长截图'); ?>" aria-pressed="false"><img class="shot-image" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($alt); ?>" loading="<?php echo esc_attr($card['imageLoading']); ?>" decoding="async" draggable="false"><?php if ($s['showHint']) : ?><span class="shot-hint"><?php echo self::icon('mouse-pointer-2'); ?><span>悬停浏览长图</span></span><?php endif; ?></button><button type="button" class="image-zoom icon-button" data-showcase-zoom aria-label="<?php echo esc_attr('放大 ' . $card['name'] . ' 截图'); ?>" title="放大截图"><?php echo self::icon('maximize-2'); ?></button><?php else : echo self::fallback(); endif; ?>
        </div>
        <div class="case-body"><?php if ($label !== '') : ?><div class="case-kicker"><?php echo esc_html($label); ?></div><?php endif; ?><h3><?php echo esc_html($card['name']); ?></h3><?php if ($card['description'] !== '') : ?><p class="case-description"><?php echo nl2br(esc_html($card['description'])); ?></p><?php endif; ?></div>
        <div class="case-actions"><?php echo self::button($card, $s, false) . self::button($card, $s, true); ?></div>
        </article>
        <?php endforeach; ?>
        </div><?php endif; ?>
        </section>
        <?php return (string) ob_get_clean();
    }
}
