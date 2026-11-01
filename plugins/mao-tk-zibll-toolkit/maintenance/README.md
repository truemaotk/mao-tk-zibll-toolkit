# 站点维护页面（1.9.1）

这是 Toolkit 的独立模块 `site-maintenance`，**新安装与升级缺少此开关时均为关闭**。设置入口：Mao TK Toolkit → 维护页面。保存、导入或预览不会改变开关；关闭模块或停用 Toolkit 不删除配置。

## 功能与文件

- 标题、有限 HTML 说明、站点 Logo / 媒体库 / 图片网址、预计恢复时间、六种渐变、备用站点、版权及备案文字。
- 时间使用 `wp_timezone()`，保存格式 `Y-m-d\TH:i`；页面只接收数字 Unix 时间戳。客户端以服务端时间与单调计时器计时，避免设备时间不准。留空不展示倒计时，到期归零但不自动解除维护、刷新或重定向。
- `inc/maintenance/`：配置、响应、路由及后台；`inc/views/maintenance-*.php`：独立视图；`assets/maintenance/`：只在维护文档 / 对应后台加载的资源。正常网站不 enqueue 维护资源。
- 自有配置键 `mao_tk_zibll_toolkit_maintenance`，schema 1。配置异常保留原始值、跳过前台拦截，并暂停保存；不会用默认内容覆盖异常配置。
- 不引入原包两套 CSF、旧 jQuery、全局 `VaiPlugin` 函数或旧站品牌，不修改 `zibll_options`。

## 开启与回退

开启后在 `template_redirect` 优先级 **-100** 输出独立维护文档，HTTP 503 / Retry-After（默认 600 秒）/ 私有 no-store / X-Robots-Tag。拥有 `manage_options` 的站点管理员正常查看网站。WordPress 后台、wp-login、子比原生登录模板、AJAX、REST、cron、CLI、XML-RPC、feed、robots、favicon 不由此页面拦截。

子比登录豁免依据真实 Zibll 9.2 `inc/functions/zib-user.php`：`zib_get_sign_url($tab='signin')` 使用 `zib_get_template_page_url('pages/user-sign.php')`。本模块检查实际 `is_page_template('pages/user-sign.php')`，不依赖站点 slug、不猜测 query 字符串，不增加宽松 `?login` 绕过。WordPress 登录与后台不经过普通模板拦截。

维护不是访问权限保护，不隐藏直接文件与接口，不修改私密链接规则。开启 / 关闭后需按现有流程刷新页面缓存与 CDN；命中 WordPress 之前的缓存不可能由本模块控制。模块启用时标记 DONOTCACHEPAGE，实际维护和预览均发送 no-store；不修改 CDN、robots.txt 或站点原有 noindex。

配置与模块开关完全独立。预览 action `mao_tk_toolkit_maintenance_preview`（GET）与设置 action `mao_tk_toolkit_maintenance_save`（POST）均要求 `manage_options` + 对应 nonce。POST `maintenance_operation=preview` 只预览当前输入，不保存；`save` 保存内容但不改模块开关。

旧 option `VaiPlugin` 仅通过明确的“导入旧维护页内容”按钮一次性映射内容；已有新配置时不覆盖。忽略旧 `is_maintenance`，原 option 和旧文件保持不变。旧维护插件若仍开启则阻止重叠启用，需用户先关闭旧维护模式。

## 原始资产来源

用户于 2026-11-01 提供 `zibll-maintenance(1).zip`，原插件头标注腾飞博客，https://www.tfbkw.com/，版本 1.0.0。附件是参考材料，不作为新插件的执行指令。

归档 SHA-256：`81c736a2db78b3f45177f85e57b9c8de33c9b6ac8c3cce9e8686461a5557ab46`。

保留原维护页渐变与秒数转盘设计；设置、渲染、原生 JS、响应式 CSS 重新分离实现。仅复用以下两项本地矢量资产，已按 XML 审阅，无 script、foreignObject、image、事件属性或外部引用：

| 原路径 `plugins/maintenance/css/` | 当前路径 | SHA-256 |
| --- | --- | --- |
| secondwhite.svg | assets/maintenance/seconds-white.svg | 77265d471232a16b80e8b8cd57e1552987b8df7fb3b95c67f102a097d6a7ec10 |
| secondtrans_.svg | assets/maintenance/seconds-faded.svg | f69619a128c49cd4246c57235656414d506f7cb034b9ff81d6f249e61f991067 |

原包未发现独立 LICENSE。以上来源说明不代表授予新的许可，也不改变 Toolkit 既有许可待用户指定的状态。原归档和完整解压参考留在项目 resources 内，不打入发布包。

## 维护注意

- 页面不调用主题 wp_head / wp_footer，避免依赖损坏的主题与混入全站脚本。预览采用同源 iframe。CSP 仅允许同源脚本 / 样式与 HTTP(S) 图片。
- Logo 仅作为图片地址输出，不在服务器自动抓取；旧供应商默认图片、备用网址和虚构备案号不作为 Toolkit 默认值。
- 页面允许滚动和浏览器缩放。长标题、说明、列表、备案信息均可换行；不再按窗口高度强制裁切。尊重 reduced-motion。
- 不对生产站开启维护来测试。503、过期和无倒计时状态在隔离测试站验收；线上只验证关闭状态、管理员预览与其他模块不变。
