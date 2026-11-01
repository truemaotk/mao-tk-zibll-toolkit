# 私密链接可见性 · 1.8.2

后台：**Mao TK Toolkit → 私密链接可见性**。导航页继续使用子比原生“Zibll-网址导航”模板；链接继续在 WordPress 的“链接”菜单管理。无需给文章、页面追加 CSS/JavaScript。

1.8.2 仅优化后台布局与操作提示：角色支持多选，显示即时已选数量；账号表格标明“已保存结果”，修改后另行提示待保存，不在客户端推断授权结果。两个表单仍分别原生 POST 提交。新增 assets/js/private-links-admin.js 仅在此页面加载；不自动提交或扩展任何可见范围。长说明复用 Toolkit 折叠，间距放在内部内容层，避免收起后残留边缘。手机布局避免表格横向拖动。

## 权限规则

- 默认开启模块；配置不存在时，仅实际注册且拥有 `manage_options` 能力的角色可见，通常为管理员。已有明确关闭不会被覆盖。
- 游客始终不可见。账号规则 `deny > allow > role`。多角色用户匹配任一允许角色即可。
- 角色来自 `wp_roles()->roles`，账号来自本站 `WP_User_Query`，每页 20 位，支持用户名／显示名称搜索。修改账号规则只更新提交的本页，保留其他页；切换分页前先保存。
- 设置管理要求 `manage_options`；账号列表和单独设置额外要求 `list_users`。不会修改原 WP 角色、会员等级、订单、付费权益或密码。
- 私密状态仍存 `link_visible=N`。后台“待审核”计数及原生提醒不会因显示而变成已审核。权限规则是全站私密链接统一规则，尚无逐链接独立 ACL。
- 配置无效时前台按不可见处理，不自动重置。停用不删除配置。

## 服务端接入边界

独立 option `mao_tk_zibll_toolkit_private_links`：schema_version=1、allowed_roles 数组、user_overrides 用户 ID 到 allow/deny 映射。不存用户名、邮件、链接 URL 副本。读取不写入；保存含 POST、权限、nonce、严格类型及实际角色／账号校验，持久化后回读确认。

`get_bookmarks`（最后优先级、2 参数）对授权前台请求用原参数重新查询 `hide_invisible=false`，保留 include/exclude、搜索、分类、排序和 limit。WordPress 核心在过滤前缓存原始查询，本模块不改缓存中的对象，不将 N 临时改 Y。原生后台列表维持原样；REST、AJAX、feed、XML-RPC、CLI 不拓宽私密数据。

已实际核查 Zibll **9.2** 的 `inc/functions/zib-links.php`：`zib_get_bookmarks($args)` 多分类分支直接 `SELECT DISTINCT l.*`，绕过核心 filter。模块的 `query` filter 只匹配该函数的调用栈与完整表／JOIN／taxonomy 查询特征，在 SQL 的 LIMIT 之前调整可见条件。其他查询不变，不覆盖主题函数。

同一主题的 `zib_link_detail_template()` 在 `template_redirect` 优先级 5 拒绝 N。本模块在优先级 4 仅处理获准用户的 N，设置原生详情所需全局数据后加载**已安装主题**的 `pages/link.php`，使用其 CSS、页头、页脚及相关链接；未授权或公开链接继续交给主题。代码不包含主题模板副本，函数／模板缺失时保守回退。尚不承诺所有历史或未来 Zibll 版本。

授权前台请求：`DONOTCACHEPAGE`、Cache-Control private/no-store、Vary Cookie。私密详情额外 noindex/nofollow/noarchive、Referrer-Policy no-referrer。共享缓存/CDN 必须尊重登录 cookie 和源站响应头；不应配置忽略 cookie 的整页缓存。权限撤销后已打开页面需要刷新，不能撤回用户此前已知的网址。

范围不包括：其他扩展手写 SQL、文章中手工写入的 URL、第三方自己的 API/搜索索引/输出缓存、外部目标网站访问权限。私密链接控制是本站展示权限，无法保护已公开的外部网址。停用模块后恢复主题原生规则。

## 维护与验证

实现目录 `inc/private-links/`、模块类 `inc/modules/class-private-links-module.php`、后台独立样式 `assets/css/private-links-admin.css`。不加载新前端资源，不改封板组件文件。

1.8.0 在隔离 WordPress 7.1.4/PHP 8.5.12/SQLite 中测试真实 `get_bookmarks` 查询和缓存、角色／账号优先级、保存校验、分类与限制、REST/AJAX/后台隔离；多分类分支采用已核对的原生 SQL 夹具。线上验证范围另见发布报告，不用夹具代替真实主题验收。回退可关闭本模块或上传保留的 1.7.0 包；原私密标记无需还原。
