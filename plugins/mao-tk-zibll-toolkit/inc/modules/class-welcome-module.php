<?php
namespace MaoTK\ZibllToolkit\Modules;

defined('ABSPATH') || exit;

final class WelcomeModule implements \MaoTK\ZibllToolkit\Module
{
    public function id(): string
    {
        return 'welcome-note';
    }

    public function name(): string
    {
        return __('工作台欢迎提示', 'mao-tk-zibll-toolkit');
    }

    public function description(): string
    {
        return __('在本页显示一条欢迎提示，用于检查模块的独立启用与加载。', 'mao-tk-zibll-toolkit');
    }

    public function requires_zibll(): bool
    {
        return true;
    }

    public function boot(): void
    {
        add_action('mao_tk_zibll_toolkit_dashboard_content', array($this, 'render'));
    }

    public function render(): void
    {
        echo '<div class="mao-tk-toolkit__welcome" role="status" data-mao-tk-welcome-output>';
        echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div>';
        echo '<strong>' . esc_html__('欢迎提示已生效', 'mao-tk-zibll-toolkit') . '</strong>';
        echo '<p>' . esc_html__('这是示例模块的实际输出。关闭此模块后，这条提示将不再显示。', 'mao-tk-zibll-toolkit') . '</p>';
        echo '</div></div>';
    }
}
