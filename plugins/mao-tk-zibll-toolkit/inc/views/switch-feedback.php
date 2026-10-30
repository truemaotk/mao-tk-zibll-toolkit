<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
/** $switch_notice is the validated result key supplied by each page. */
$switch_messages = array(
    'saved' => '模块开关已保存，实际加载状态已更新。',
    'save-failed' => '模块开关未能保存，请检查配置状态与数据库连接后重试。',
);
$switch_message = $switch_messages[$switch_notice] ?? '';
?>
<div class="mao-tk-toolkit__switch-feedback" data-mao-tk-switch-feedback data-feedback-state="<?php echo esc_attr($switch_notice); ?>" role="status" aria-live="polite" aria-atomic="true" <?php if ($switch_message === '') { echo 'hidden'; } ?>><?php echo esc_html($switch_message); ?></div>
