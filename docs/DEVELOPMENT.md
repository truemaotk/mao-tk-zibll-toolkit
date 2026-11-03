# 开发与验证

先阅读根 README、RIGHTS.md 和各模块 README。当前公开目录导出 1.20.0 正式生产源码及 1.3.0 写作技能，不含特定开发机器的浏览器夹具、数据库或部署配置。

## 边界

1. 保留命名空间 `MaoTK\ZibllToolkit`、唯一资源句柄和模块作用域。
2. 不覆盖子比主题文件、全局样式、鉴权、下载与支付行为。
3. 后台写操作保留权限、nonce、输入清理和输出转义；不得把 nonce 当成权限。
4. 文章只保存组件结构；标题和目录用主题原生，文章内不添加 CSS / JS。
5. 已确认的模块样式不能被新模块顺带覆盖。前后台资源按页面和开关加载。
6. 原生代码语法色归子比 Enlighter，代码主题字段留空；提示颜色默认继承后台，单块覆盖优先。
7. 案例截图弹窗使用专属属性 `data-mao-tk-showcase-close`，不要改回会被子比全局 AJAX 监听的 `data-action`。
8. 原生标记兼容层只处理已核实的 Zibll 前台完整 HTML。保留导航自动宽度和已有显式属性；评论辅助框只补唯一 ID，不新增 name。不得扩展成正文重写、表单接管、通用图片处理器或第三方脚本过滤器。

## 本地检查

安装者自行准备 PHP、Node.js、Python 3；本项目不自动安装软件或更改系统环境。

- 对修改的 PHP 执行 `php -l <file.php>`。
- 对修改的 JavaScript 执行 `node --check <file.js>`。
- 技能检查器：`python skills/maotk-toolkit-articles/scripts/check_article.py <article.blocks.html>`。
- 技能检查器回归：在其 scripts 目录运行 `python -m unittest test_check_article.py`。
- 安装包构建：从仓库根目录运行 `powershell -File tools/build-release.ps1`。

构建工具只生成两个 ZIP 与 SHA256SUMS，不会部署、发布文章、删除文件或连接生产网站。再次构建请换空的输出目录，避免覆盖历史产物。

## 真实环境回归

在隔离 WordPress + 已授权 Zibll 环境验证启停、保存读回、主题依赖缺失、明确关闭保留、浅深模式、窄屏溢出、原生工具与页面作用域。浏览器静态页面或语法检查不等于 WordPress 兼容测试。

案例模块重点验证：长图自然高度、连续多次打开关闭、Esc / 遮罩 / 关闭按钮、0px 留白、上下独立保存、分类模板和旧区块兼容。不要为了测试实际付款、改变用户权限或覆盖生产案例。

1.7.0 原生标记层重点验证：主题升级后的输出结构、桌面/手机 Logo 几何尺寸、六类认证字段语义、辅助框 ID 冲突、既有 name/type/value/action 保留、重复处理幂等，以及后台、REST/AJAX、非 HTML、流式响应隔离。缺少 WordPress HTML API 时安全跳过。开发者可通过 `mao_tk_toolkit_native_markup_compat_enabled` 过滤器单独停用这一兼容层。


## 1.20.0 验收与边界

邮件模块适配已提供的 Zibll 9.2 通知契约。本地 WordPress 7.1.4／PHP 8.5.12／SQLite QA 环境通过原生邮件 68 项、回复与币种 65 项、浏览器 69 项、配置备份 54 项，124 个生产 PHP 文件语法检查通过。通过拦截 wp_mail 防止向外发送；不代表生产 MySQL、全主题或 Gmail／Outlook／QQ／163 实际收件已验收。

配置、媒体、源码和模块开关必须分开处理。已明确的开关值保留；系统邮件默认关闭。通用变更仅影响继承字段，独立空值及 0 保留，模式切换保留独立配置。接管邮件才能替换 Reply-To，未知来源、原生模式、完整 HTML／第三方模板和关闭模块不改发送逻辑。

制包时的模块 README 包含本地验收状态，当前发布状态以发行说明为准。仓库不附带真实站点配置、可选本站配置 JSON、测试账号或生产数据。SKILL 保持 1.3.0。
