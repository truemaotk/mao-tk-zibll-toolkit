# 头像与背景预设（1.10.0）

模块 ID：`user-profile-presets`。后台：Mao TK Toolkit → 头像与背景。新安装或升级缺少该开关时默认启用；明确关闭保留。依赖 Zibll 的用户资料 API，其他主题、接口缺失、配置异常或旧同类插件仍运行时，不接管前台。

## 使用与数据

- 内置 **357 个去重头像、8 张背景**，文件原样本地化。头像 / 背景可单独关闭，也可关闭内置库、仅使用管理员从媒体库选择的图片；每类自定义图片最多 200 张。
- 本人的个人主页菜单、用户中心头部新增选择入口；每页 24 张，支持分页、随机选择、明确确认和取消。随机选择只选中图片，确认后才保存。不会取代子比原生上传入口。
- 注册时随机头像单独控制，默认关闭。开启后仅对新注册且无自定义头像的账号生效，不自动分配背景、不批量修改已有账号。
- 配置键：`mao_tk_toolkit_profile_presets`，schema 1，独立 revision 识别过期表单。设置和模块开关分离；保存设置不修改其他模块的选项。
- 用户确认后通过主题原生 `zib_update_user_meta()` 更新 `custom_avatar` / `cover_image`，相应 `_id` 存 **0**，并在插件自身 user meta 中记录预设 ID。0 表示复用的静态预设，不伪造为附件 1，也不将共享媒体附件编号写成用户专属上传附件。
- 不删除此前的头像 / 封面附件；写入失败会尝试恢复此前字段。重复选择当前图片仍可成功。取消不写入；移出图库不删除媒体。
- 更新图片库时必须保留既有 ID 和文件路径，不重新编号或覆盖在用图片。停用保留设置和用户资料；删除 Toolkit 文件会使使用中的静态预设 URL 失效，卸载前应先迁移这些引用。

## 实际 Zibll 9.2 接口依据

2026-11-01 从已登录主题编辑器只读核对，未修改主题：

| 来源 | 已核对契约 |
| --- | --- |
| `inc/functions/zib-author.php:718` | `zib_get_author_header_dropup_btn()`，732 行 `author_header_drop_lists` 接收菜单字符串与 author ID。 |
| `inc/functions/user/page.php:15` | `zib_user_page_header()`，52 行 `user_page_header_desc` 接收说明字符串和当前 user ID。原生头像 / 封面上传入口保持。 |
| `inc/functions/user/page/user-center.php` | 在 `get_header()` 之前触发 `locate_template_user_center`。用于按需准备选择器资源，不猜测用户中心 slug。 |
| `inc/dependent.php:328` / `:339` | `zib_get_user_meta($id,$key,$single=false)` / `zib_update_user_meta($id,$key,$value)`；头像、背景字段属于主题自身 option-meta 存储，应调用主题 API，不直接写同名 WP user meta。 |
| `action/user.php:196` / `:248` | 原生上传修改 `custom_avatar[_id]`、`cover_image[_id]`，成功后使用 `.avatar-id-{id}`、`.user-cover-id-{id}` 更新图片。原上传会删除旧非零附件编号，因此静态共享预设必须使用 0。 |

原生 API 在 `after_setup_theme` 的 Toolkit 启动阶段检测。`wp` 准备自己的 author 页，`locate_template_user_center` 在头部前准备用户中心；`wp_enqueue_scripts` 只在已准备页面加载本模块 CSS / 原生 JS。Native `<dialog>` 独立管理焦点、取消、分页和反馈，不依赖旧混淆脚本、Bootstrap modal 或 notyf，也不使用子比全局 `[data-action]` 事件。

主题的头像 URL 最终渲染实现未复制到插件中。写入依赖原生资料 API，并做同 API 读回检查。生产验收不随意替换现有管理员头像；资料写入覆盖使用项目内隔离 WordPress 的 API 适配替身完成，不将其标为完整真实主题存储引擎测试。

## 接口边界

仅注册已登录 AJAX：`mao_tk_toolkit_profile_presets`，POST；同时检查登录、`read` 能力、专用 nonce、有效配置和各类开关。`operation=list` 返回当前类的分页；`operation=save` 只接收服务端图库的 `preset_id`。保存对象固定为当前账号，忽略外部 user_id，不接受任意 URL 或文件路径。

媒体库仅接受有效的本地 JPG / PNG / WebP / GIF 附件，SVG、非附件、回收站及 uploads 之外文件不进入图库。未新增远程图片抓取接口。当前不支持仅保存在远端、没有本地副本的媒体卸载 / 对象存储附件。

配置异常会停止前台入口和保存，保留原数据。旧全局 `zibll_avatar_get_option()` 仍存在时暂停前台模块，防止同类插件同时运行；旧 `zibll_avatar_options` 和自定义头像目录不被覆盖、清理或自动导入。需要旧目录的额外图片时，从媒体库添加即可。

## 原包与资产来源

用户提供 `子比插件-选择预设头像+背景.zip`，归档 SHA-256：`3ff688b9f5f277f202605e5eb9f0b8197d687b963ad212b1c36406b895aa49f0`。包中包含头像版 1.0.0 和头像＋背景版头标 1.1.0，头部署名腾飞博客，https://www.tfbkw.com/。

原包 `tx/` 有 358 个头像文件，其中一对内容重复；保留 357 个独立头像。新版 `img/` 有 8 张背景。图片经格式 / 尺寸解码检查，按原字节复制，未缩放、压缩或外链依赖。Mac 元数据、旧 PHP、混淆 JS、旧 CSS 和卸载清理脚本均不进入新模块。

映射清单保存在开发产物 `asset-provenance.json`，发布端 `assets/profile-presets/catalog.json` 只含稳定 ID、类型、路径与尺寸。原包未发现整体 LICENSE；本次接入不替原作者指定再分发许可，不扩展用户此前暂不授予开源许可的决定。

## 验收范围与回退

PHP / JS 语法、配置校验、分页、白名单、权限 / nonce、自己的账号限制、资料保存与失败恢复、注册开关在隔离数据库验证；真实站点核对菜单入口、资源与图片加载、选择 / 分页 / 取消、后台设置。上线不批量更改用户图片，不修改主题、文章正文、私密链接规则或维护开关。

关闭模块即可恢复仅原生入口，配置与已经保存的预设仍保留。必要时可回退之前的 Toolkit 安装包；若旧包不含新增图片文件，应同时保留本模块静态资产或先迁移使用中的图片 URL。
