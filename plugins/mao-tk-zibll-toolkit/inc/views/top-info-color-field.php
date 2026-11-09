<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;
$color_ids = array('bg_color' => 'mao-tk-bg', 'text_color' => 'mao-tk-text', 'icon_color' => 'mao-tk-icon');
$color_id = $color_ids[$color_key];
$opacity_key = str_replace('_color', '_opacity', $color_key);
?>
<fieldset class="mao-tk-top-info__color-field" data-color-field="<?php echo esc_attr($color_key); ?>">
    <legend><?php echo esc_html($color_label); ?></legend>
    <div class="mao-tk-top-info__color-row">
        <button type="button" class="button mao-tk-top-info__color-toggle" data-color-toggle aria-label="<?php echo esc_attr($color_label . __('调色盘', 'mao-tk-zibll-toolkit')); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr($color_id . '-panel'); ?>"></button>
        <input id="<?php echo esc_attr($color_id); ?>" name="top_info[<?php echo esc_attr($color_key); ?>]" type="text" data-color-value value="<?php echo esc_attr($settings[$color_key]); ?>" pattern="#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?" maxlength="7" required autocomplete="off" spellcheck="false" aria-label="<?php echo esc_attr($color_label . __('十六进制值', 'mao-tk-zibll-toolkit')); ?>">
    </div>
    <div class="mao-tk-top-info__color-panel" id="<?php echo esc_attr($color_id . '-panel'); ?>" data-color-panel hidden>
        <div class="mao-tk-top-info__hsv-grid">
            <div class="mao-tk-top-info__sv" data-color-sv tabindex="0" role="group" aria-label="<?php echo esc_attr($color_label . __('饱和度和明度色板', 'mao-tk-zibll-toolkit')); ?>" aria-describedby="<?php echo esc_attr($color_id . '-drag-help'); ?>"><span class="mao-tk-top-info__sv-handle" data-color-handle aria-hidden="true"></span></div>
            <input class="mao-tk-top-info__hue" type="range" data-color-hue min="0" max="359" step="1" value="0" orient="vertical" aria-label="<?php echo esc_attr($color_label . __('色相', 'mao-tk-zibll-toolkit')); ?>">
        </div>
        <span class="description" data-color-hsv></span>
        <p class="description" id="<?php echo esc_attr($color_id . '-drag-help'); ?>"><?php esc_html_e('拖动色板选择颜色，右侧拖动色相。方向键微调，Shift 加速，Esc 收起。', 'mao-tk-zibll-toolkit'); ?></p>
    </div>
    <div class="mao-tk-top-info__swatches" role="group" aria-label="<?php echo esc_attr($color_label . __('常用颜色', 'mao-tk-zibll-toolkit')); ?>">
        <?php foreach (array('#425aef', '#163b32', '#f08c00', '#d6336c', '#ffffff', '#111827') as $swatch) : ?>
            <button type="button" class="button" data-color-swatch="<?php echo esc_attr($swatch); ?>" style="--mao-tk-swatch:<?php echo esc_attr($swatch); ?>" aria-label="<?php echo esc_attr(__('使用颜色 ', 'mao-tk-zibll-toolkit') . $swatch); ?>"></button>
        <?php endforeach; ?>
    </div>
    <label class="mao-tk-top-info__alpha-label" for="<?php echo esc_attr($color_id . '-opacity'); ?>"><?php esc_html_e('不透明度', 'mao-tk-zibll-toolkit'); ?> <output data-color-output for="<?php echo esc_attr($color_id . '-opacity'); ?>"><?php echo esc_html((string) $settings[$opacity_key]); ?>%</output></label>
    <input id="<?php echo esc_attr($color_id . '-opacity'); ?>" class="mao-tk-top-info__alpha" type="range" name="top_info[<?php echo esc_attr($opacity_key); ?>]" data-color-opacity min="0" max="100" step="1" value="<?php echo esc_attr((string) $settings[$opacity_key]); ?>">
    <p class="description"><?php esc_html_e('点击色块展开拖动调色盘。0% 全透明，100% 不透明。', 'mao-tk-zibll-toolkit'); ?></p>
</fieldset>
