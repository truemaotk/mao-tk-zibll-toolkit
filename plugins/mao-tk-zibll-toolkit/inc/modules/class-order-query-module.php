<?php
namespace MaoTK\ZibllToolkit\Modules;
use MaoTK\ZibllToolkit\Module;
use MaoTK\ZibllToolkit\OrderQuery\Runtime;
defined('ABSPATH') || exit;
final class OrderQueryModule implements Module
{
    public function id(): string { return 'page-order-query'; }
    public function name(): string { return '订单查询页面'; }
    public function description(): string { return '提供独立页面模板，按账号归属或子比原生游客凭据查询订单，保留原生详情和支付入口。'; }
    public function requires_zibll(): bool { return true; }
    public function boot(): void { Runtime::enable(); }
}
