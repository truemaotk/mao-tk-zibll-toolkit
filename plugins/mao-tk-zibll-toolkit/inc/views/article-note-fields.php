<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
/** $group, $fields, $resolved, $icons are supplied by the settings view. */
$labels = array('light' => __('浅色模式颜色', 'mao-tk-zibll-toolkit'), 'dark' => __('深色模式颜色', 'mao-tk-zibll-toolkit'), 'badgePosition' => __('标签位置', 'mao-tk-zibll-toolkit'), 'iconPosition' => __('图标位置', 'mao-tk-zibll-toolkit'), 'iconKey' => __('本地图标', 'mao-tk-zibll-toolkit'), 'iconSize' => __('图标尺寸', 'mao-tk-zibll-toolkit'), 'showIcon' => __('显示图标', 'mao-tk-zibll-toolkit'), 'titleColor' => __('内部标题颜色', 'mao-tk-zibll-toolkit'));
foreach ($labels as $key => $label) :
    $value = array_key_exists($key, $fields) ? $fields[$key] : '';
    if (is_bool($value)) { $value = $value ? 'true' : 'false'; }
    $id = 'mao-tk-note-' . $group . '-' . $key;
    $name = $group === 'defaults' ? 'note[defaults][' . $key . ']' : 'note[types][' . $group . '][' . $key . ']';
?>
<div class="mao-tk-note-field<?php echo in_array($key, array('light', 'dark'), true) ? ' mao-tk-note-field--color' : ''; ?>"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
<?php if (in_array($key, array('light', 'dark'), true)) : ?>
    <div class="mao-tk-note-color-control"><button class="mao-tk-note-swatch" type="button" data-note-color-trigger="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr($title . '，' . $label . __('，打开调色盘', 'mao-tk-zibll-toolkit')); ?>" aria-haspopup="dialog" aria-controls="mao-tk-note-palette" aria-expanded="false" style="--mao-tk-note-swatch:<?php echo esc_attr($resolved[$key]); ?>"></button><input class="mao-tk-note-color" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" type="text" placeholder="<?php echo esc_attr($resolved[$key]); ?>" pattern="#[0-9a-fA-F]{6}" autocomplete="off" spellcheck="false" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="<?php echo esc_attr($key); ?>"><button type="button" class="mao-tk-note-inherit" data-note-color-inherit="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr($title . '，' . $label . __('恢复继承', 'mao-tk-zibll-toolkit')); ?>"><?php esc_html_e('继承', 'mao-tk-zibll-toolkit'); ?></button></div>
<?php elseif ($key === 'iconSize') : ?>
    <div class="mao-tk-note-size"><input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="number" min="12" max="28" step="1" value="<?php echo esc_attr((string) $value); ?>" placeholder="<?php echo esc_attr((string) $resolved[$key]); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="iconSize"><span>px</span><input type="range" min="12" max="28" value="<?php echo esc_attr((string) $resolved[$key]); ?>" aria-label="<?php echo esc_attr($group . __('图标尺寸滑块', 'mao-tk-zibll-toolkit')); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-size-range></div>
<?php else :
    $options = array();
    if ($key === 'badgePosition') { $options = array('left' => __('左', 'mao-tk-zibll-toolkit'), 'center' => __('中', 'mao-tk-zibll-toolkit'), 'right' => __('右', 'mao-tk-zibll-toolkit')); }
    elseif ($key === 'iconPosition') { $options = array('before' => __('文字前', 'mao-tk-zibll-toolkit'), 'after' => __('文字后', 'mao-tk-zibll-toolkit')); }
    elseif ($key === 'showIcon') { $options = array('true' => __('显示', 'mao-tk-zibll-toolkit'), 'false' => __('隐藏', 'mao-tk-zibll-toolkit')); }
    elseif ($key === 'titleColor') { $options = array('type' => __('跟随类型色', 'mao-tk-zibll-toolkit'), 'neutral' => __('正文中性色', 'mao-tk-zibll-toolkit')); }
    elseif ($key === 'iconKey') { foreach ($icons as $icon_key => $icon) { $options[$icon_key] = translate($icon['label'], 'mao-tk-zibll-toolkit'); } }
?>
    <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="<?php echo esc_attr($key); ?>"><option value=""><?php esc_html_e('继承', 'mao-tk-zibll-toolkit'); ?><?php echo $group === 'defaults' ? __('封板默认', 'mao-tk-zibll-toolkit') : __('通用设置', 'mao-tk-zibll-toolkit'); ?></option><?php foreach ($options as $option => $text) : ?><option value="<?php echo esc_attr($option); ?>" <?php echo (string) $option === (string) $value ? 'selected' : ''; ?>><?php echo esc_html($text); ?></option><?php endforeach; ?></select>
<?php endif; ?>
</div>
<?php endforeach; ?>
