<?php
use MaoTK\ZibllToolkit\R2Downloads\Settings;

defined('ABSPATH') || exit;

$migration_ready = Settings::has_dedicated_key() && Settings::can_encrypt();
?>
<span><?php echo esc_html($migration_ready
    ? __('在轮换 WordPress 安全密钥前，可直接迁移本页已保存的凭据。', 'mao-tk-zibll-toolkit')
    : __('迁移前请在服务器配置 MAO_TK_TOOLKIT_SECRET_KEY 并启用 OpenSSL。', 'mao-tk-zibll-toolkit')); ?></span>
<button type="submit" class="button" <?php disabled(!$migration_ready); ?>><?php esc_html_e('迁移已保存凭据', 'mao-tk-zibll-toolkit'); ?></button>
