# 订单查询模块（1.11.0）

在 Toolkit → 订单查询设置标题、说明、客服链接及最近查询记录。新建 WordPress 页面时选模板 **MaoTK 订单查询**；也可使用该设置页“创建并发布订单查询页面”。不会自动加导航菜单，不修改主题文件。

模块 `page-order-query` 缺省启用，旧模块明确保存的开关保持。关闭后页面仍存在但显示暂不可用，配置及订单保留。切换非 Zibll 主题时停止接入；回到 Zibll 或其子主题可恢复。插件停用后模板需切换为其他模板或停用该页面，插件文件不复制进主题目录。

## 权限和数据

- 登录用户仅查询自己账号的订单，包括管理员；全站订单仍使用子比商城后台。
- 支持订单号、支付单号、订单数据内的联系方式；完整合并支付编号通过子比 payment 接口解析，后续查询仍限制 user_id。
- 游客只访问同一浏览器持有的有效子比原生 `order_hmac_*` 凭据对应的匿名订单；cookie 名称或手机号不构成权限。原生凭据失效需联系客服，不提供绕过验证的恢复入口。
- 查询必须 POST + 页面 nonce，入口必须是可访问的订单查询页面，最多每页 20 条；关键词、页码、模式及设置类型均校验。短期频率限制不储存明文 IP 或关键词。
- 最近记录仅存当前用户最多 10 个订单 ID，每次读取重新验证归属。关闭记录保存不删除已有记录，清空按钮只清查询记录。
- 支付、详情、售后、权益、订单状态继续由子比负责。本模块不调用会触发超时关单的 `zibpay_get_order_status`，不创建交易或改变订单金额。
- 查询页私有 no-store，AJAX 禁用缓存；CDN/反向代理仍须排除查询页和 admin-ajax.php。页面 HTML 不预置任何订单数据或签名凭据。

## 文件和接入

`inc/order-query/`：设置、原生只读适配、归属校验、模板、AJAX 和渲染。
`assets/order-query/`：前台和后台独立样式及前台脚本，仅对应页面加载。
`inc/modules/class-order-query-module.php`：模块开关。
`inc/views/order-query-settings.php`：设置界面。
独立选项 `mao_tk_toolkit_order_query`，不改 zibll_options、用户角色、其他模块设置。设置修订号检查防止旧窗口覆盖新配置。

## 已核对的 Zibll 9.2 接口

实际站点主题文件编辑器只读核对（2026-11-01）：

- `zibpay/class/order-class.php`：`ZibPay::order_query($args)`；`user_id/id/payment_id` 条件与 `search_columns`、`s`、`paged/per_page`；返回 orders/total。`get_order`、`get_row`、`get_payment`。
- `zibpay/functions/ajax.php` 85–103：`order_details_modal` 使用 `zibpay_verify_order_permission($order)`；匿名与登录请求均注册。凭据验证由主题负责，未复制或解密主题受保护实现。
- `zibpay/functions/zibpay-order.php` 611–634：匿名下单创建签名、有效期限定的 `order_hmac_ID` cookie。
- `zibpay/functions/zibpay-user.php` 1086–1110：`zibpay_get_order_pay_link` 返回原生支付弹窗入口；未主动支付。
- `zibpay/functions/zibpay-func.php` 1392–1425：状态辅助函数可能写入超时关单，因此查询不调用它。

## 验证与回退

独立 WordPress 7.1.4 / SQLite 验证查询归属、分页、设置、nonce、异常请求、原生接口缺失和模块独立性；Zibll 接口使用依据实源码的契约替身。生产站验证真实子比模板、前后台与空查询，不制造真实订单或支付。详细报告在工作区 output/order-query-1.11.0-20261101。

回退：工作台关闭“订单查询页面”即可暂停；如需回退整个插件，上传已备份的 1.10.0 安装包，勿删除订单。新页面可改为草稿。历史与新设置可保留供升级恢复。教程、GitHub 与 SKILL 本轮不变更。
