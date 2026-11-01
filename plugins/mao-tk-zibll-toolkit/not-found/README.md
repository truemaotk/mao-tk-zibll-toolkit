# 404 公益页面（1.12.1）

后台：Toolkit → **404 公益页面**。独立模块 `site-not-found` 缺省启用；明确关闭的值保留。关闭后真实请求立即恢复子比原生 404，不删除设置或修改主题文件。可分别控制公益信息、照片、搜索，设置页面文字；原生搜索还遵循子比 `404_search_s` 设置。

## 已核实的接入方式

2026-11-01 只读核查本站 Zibll 9.2：`404.php` 调用 get_header → get_template_part('template/content-404') → get_footer；原生 content-404.php 以 `_pz('404_search_s', true)` 控制 `zib_get_main_search()`。

使用 WordPress 核心 `404_template` 过滤器，优先级 90、1 参数，保留真实 HTTP 404、原生页头/页脚/搜索。仅 HTML 404；后台、JSON、REST、AJAX、Feed、嵌入请求跳过。管理预览通过 template_include 95，必须具备 manage_options 且持有效 nonce；预览返回 200 并禁止缓存，关闭正式模块后仍可预览。预览不会开启模块。

## 第三方来源与安全

已逐个实际请求并核对两种数据结构（2026-11-02），后台仅能选择这两个固定接口，不接受任意网址：

- 默认 **寻鲸录**：`https://api.xunjinlu.fun/api/babygome/index.php?type=json`。HTTP 200 JSON，data 字段 name/sex/birthDay/lostDay/lostAddress/feature/photoUrl/detailUrl；照片来自 so.baobeihuijia.com，详情来自 www.baobeihuijia.com，原始发布来源为**宝贝回家**。此接口为第三方，不冒称宝贝回家官方 API。
- 备用 **优创**：`https://apis.uctb.cn/api/babyHome`。data 字段 image/name/detail_url/gender/missing_place/missing_date/birth_date；目前原始发布来源为**让爱回家公益寻人网**，并非附件标题中的宝贝回家。

默认标题“公益寻亲信息”。实际来源标签根据已校验的详情域名生成。字段适配同时支持以上两种命名方式，缺失字段不伪造。管理员可“刷新公益信息”，并看到连接超时、证书失败、异常状态、无效数据等明确状态；访客不显示服务器错误细节。

固定 HTTPS 地址由 WordPress `wp_safe_remote_get` 请求，TLS 校验开启，前台超时设置 2 秒、后台手动刷新 5 秒，不跟随重定向，响应上限 128KiB；不发送用户、账号、Cookie、访问路径或查询词。图片/详情仅接受已核实的 rangaihuijia.com、www.rangaihuijia.com、baobeihuijia.com、www.baobeihuijia.com、so.baobeihuijia.com 的 HTTPS 公共 URL；无任意服务器抓取或远程脚本执行。浏览器直接加载来源照片，禁用 Referer，提供明确宽高与懒加载；关闭照片可以停止此加载。

所有数据清理并转义，链接校验，缓存读取再次校验。成功缓存 15 分钟，失败退避 1 分钟，最多保留 6 小时近期缓存并明确标注；缓存记录标明接口归属，切换接口不会混用旧缓存。无可用数据时显示空态，404/返回首页/搜索保持。接口异常不会向访客显示 cURL 错误或服务器信息。关闭公益开关后不会请求接口。联系方式请在来源页面核实，不镜像发布电话资料。

## 数据、文件与回退

- 自有选项：`mao_tk_toolkit_not_found`；配置修订号检查防止旧设置窗口覆盖新配置。格式异常时保留原值并暂停正式替换。
- 短期 transient：`mao_tk_nf_public_feed_v1`、`mao_tk_nf_public_stale_v1`、`mao_tk_nf_public_retry_v1`、`mao_tk_nf_public_status_v1`。只保存第三方公开信息与连接状态，不存访问者资料。
- `inc/not-found/`：设置、来源适配、过滤器、模板、管理页控制器。
- `assets/not-found/`：独立前台/后台 CSS；无新增前台 JS，文章中无需插入任何代码。
- `inc/modules/class-not-found-module.php` / `inc/views/not-found-settings.php`：模块开关与设置视图。
- 关闭模块即可回退原生；停用保留配置，必要时可上传已备份的 Toolkit 1.11.1。原 content-404.php 未改写，本地另存了只读备份。

教程、SKILL、GitHub 本轮不更新。第三方信息准确性、更新及时性和未来接口结构不由本插件保证；来源适配有变时应修插件适配器，不修改文章或主题文件。
