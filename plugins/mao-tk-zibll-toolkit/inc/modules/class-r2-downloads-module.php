<?php
namespace MaoTK\ZibllToolkit\Modules;

use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\R2Downloads\Runtime;

defined('ABSPATH') || exit;

final class R2DownloadsModule implements Module
{
    public function id(): string { return Runtime::MODULE; }
    public function name(): string { return __('R2 文件上传与下载', 'mao-tk-zibll-toolkit'); }
    public function description(): string { return __('大文件分片直传私有 R2，使用子比原生下载入口签发临时链接。', 'mao-tk-zibll-toolkit'); }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::register(); }
}
