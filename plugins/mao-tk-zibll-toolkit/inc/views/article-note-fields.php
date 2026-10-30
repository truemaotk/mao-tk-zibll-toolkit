<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
/** $group, $fields, $resolved, $icons are supplied by the settings view. */
$labels = array('light' => '浅色模式颜色', 'dark' => '深色模式颜色', 'badgePosition' => '标签位置', 'iconPosition' => '图标位置', 'iconKey' => '本地图标', 'iconSize' => '图标尺寸', 'showIcon' => '显示图标', 'titleColor' => '内部标题颜色');
foreach ($labels as $key => $label) :
    $value = array_key_exists($key, $fields) ? $fields[$key] : '';
    if (is_bool($value)) { $value = $value ? 'true' : 'false'; }
    $id = 'mao-tk-note-' . $group . '-' . $key;
    $name = $group === 'defaults' ? 'note[defaults][' . $key . ']' : 'note[types][' . $group . '][' . $key . ']';
?>
<div class="mao-tk-note-field<?php echo in_array($key, array('light', 'dark'), true) ? ' mao-tk-note-field--color' : ''; ?>"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
<?php if (in_array($key, array('light', 'dark'), true)) : ?>
    <div class="mao-tk-note-color-control"><button class="mao-tk-note-swatch" type="button" data-note-color-trigger="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr($title . '，' . $label . '，打开调色盘'); ?>" aria-haspopup="dialog" aria-controls="mao-tk-note-palette" aria-expanded="false" style="--mao-tk-note-swatch:<?php echo esc_attr($resolved[$key]); ?>"></button><input class="mao-tk-note-color" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" type="text" placeholder="<?php echo esc_attr($resolved[$key]); ?>" pattern="#[0-9a-fA-F]{6}" autocomplete="off" spellcheck="false" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="<?php echo esc_attr($key); ?>"><button type="button" class="mao-tk-note-inherit" data-note-color-inherit="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr($title . '，' . $label . '恢复继承'); ?>">继承</button></div>
<?php elseif ($key === 'iconSize') : ?>
    <div class="mao-tk-note-size"><input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="number" min="12" max="28" step="1" value="<?php echo esc_attr((string) $value); ?>" placeholder="<?php echo esc_attr((string) $resolved[$key]); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="iconSize"><span>px</span><input type="range" min="12" max="28" value="<?php echo esc_attr((string) $resolved[$key]); ?>" aria-label="<?php echo esc_attr($group . '图标尺寸滑块'); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-size-range></div>
<?php else :
    $options = array();
    if ($key === 'badgePosition') { $options = array('left' => '左', 'center' => '中', 'right' => '右'); }
    elseif ($key === 'iconPosition') { $options = array('before' => '文字前', 'after' => '文字后'); }
    elseif ($key === 'showIcon') { $options = array('true' => '显示', 'false' => '隐藏'); }
    elseif ($key === 'titleColor') { $options = array('type' => '跟随类型色', 'neutral' => '正文中性色'); }
    elseif ($key === 'iconKey') { foreach ($icons as $icon_key => $icon) { $options[$icon_key] = $icon['label']; } }
?>
    <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" data-note-group="<?php echo esc_attr($group); ?>" data-note-field="<?php echo esc_attr($key); ?>"><option value="">继承<?php echo $group === 'defaults' ? '封板默认' : '通用设置'; ?></option><?php foreach ($options as $option => $text) : ?><option value="<?php echo esc_attr($option); ?>" <?php echo (string) $option === (string) $value ? 'selected' : ''; ?>><?php echo esc_html($text); ?></option><?php endforeach; ?></select>
<?php endif; ?>
</div>
<?php endforeach; ?>
