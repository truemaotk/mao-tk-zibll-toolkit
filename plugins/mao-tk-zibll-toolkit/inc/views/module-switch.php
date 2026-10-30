<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
/** $switch_module, $switch_valid and $switch_return_page are supplied by the view. */
?>
<form class="mao-tk-toolkit__module-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-mao-tk-module-form data-module-label="<?php echo esc_attr($switch_module['name']); ?>" data-state-url="<?php echo esc_url(admin_url('admin.php?page=' . $switch_return_page)); ?>">
    <input type="hidden" name="action" value="<?php echo esc_attr(Admin::ACTION); ?>">
    <input type="hidden" name="module_id" value="<?php echo esc_attr($switch_module['id']); ?>">
    <input type="hidden" name="enabled" value="<?php echo $switch_module['enabled'] ? '0' : '1'; ?>">
    <input type="hidden" name="return_page" value="<?php echo esc_attr($switch_return_page); ?>">
    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(Admin::ACTION)); ?>">
    <button type="submit" class="mao-tk-toolkit__switch" role="switch" aria-checked="<?php echo $switch_module['enabled'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($switch_module['name'] . '开关'); ?>" <?php disabled(!$switch_valid); ?>>
        <span class="mao-tk-toolkit__switch-track" aria-hidden="true"></span>
    </button>
</form>
