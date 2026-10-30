<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;
$spacing_defaults = Settings::spacing_defaults();
?>
<fieldset class="mao-tk-top-info__spacing">
    <legend>横幅上下留白（px）</legend>
    <p class="description">拖动或输入 0—200。选择右侧对应设备查看效果，保存设置后应用到正式页面。</p>
    <?php foreach (array('desktop' => array('桌面', '1025px 及以上'), 'tablet' => array('平板', '768—1024px'), 'mobile' => array('手机', '767px 及以下')) as $spacing_device => $spacing_label) : ?>
        <fieldset class="mao-tk-top-info__spacing-device"><legend><?php echo esc_html($spacing_label[0] . ' · ' . $spacing_label[1]); ?></legend><div class="mao-tk-top-info__spacing-grid">
            <?php foreach (array('top' => '上方留白', 'bottom' => '下方留白') as $spacing_side => $spacing_caption) : ?>
                <?php $spacing_key = 'padding_' . $spacing_side . '_' . $spacing_device; $spacing_id = 'mao-tk-spacing-' . $spacing_device . '-' . $spacing_side; ?>
                <div class="mao-tk-top-info__spacing-control" data-spacing-key="<?php echo esc_attr($spacing_key); ?>">
                    <label for="<?php echo esc_attr($spacing_id); ?>"><?php echo esc_html($spacing_caption); ?></label>
                    <input type="number" id="<?php echo esc_attr($spacing_id); ?>" name="top_info[<?php echo esc_attr($spacing_key); ?>]" min="0" max="200" step="1" required value="<?php echo esc_attr((string) $settings[$spacing_key]); ?>" aria-label="<?php echo esc_attr($spacing_label[0] . $spacing_caption); ?>" data-spacing-value data-spacing-default="<?php echo esc_attr((string) $spacing_defaults[$spacing_key]); ?>">
                    <input type="range" min="0" max="200" step="1" value="<?php echo esc_attr((string) $settings[$spacing_key]); ?>" aria-label="<?php echo esc_attr('拖动调节' . $spacing_label[0] . $spacing_caption); ?>" data-spacing-range>
                </div>
            <?php endforeach; ?>
        </div></fieldset>
    <?php endforeach; ?>
    <button type="button" class="button" data-spacing-reset>恢复默认留白</button>
</fieldset>
