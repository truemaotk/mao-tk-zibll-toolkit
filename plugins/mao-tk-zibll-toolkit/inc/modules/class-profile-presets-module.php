<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\ProfilePresets\Settings;
use MaoTK\ZibllToolkit\ProfilePresets\Runtime;
defined('ABSPATH') || exit;
final class ProfilePresetsModule implements Module
{
    public function id(): string { return Settings::MODULE; }
    public function name(): string { return __('用户头像与背景预设', 'mao-tk-zibll-toolkit'); }
    public function description(): string { return __('为个人主页与用户中心增加预设图片选择，保留原生上传；头像、背景与注册随机分配分别管理。', 'mao-tk-zibll-toolkit'); }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::register(); }
}
