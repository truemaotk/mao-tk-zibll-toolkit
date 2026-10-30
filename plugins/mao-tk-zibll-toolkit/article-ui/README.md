# 文章 UI 组件 · Toolkit 1.5.10

原始封板基线均为 **1.0.0（2026-10-29）**；宿主接入适配版本为 **1.0.1（2026-10-30）**。用户于2026-10-30单独更新代码外壳配色，代码视觉修订为 **1.0.1**；原始封板包保留，其他组件不变。这是现有 Toolkit 的组成部分，不是另一份插件或 Skill。

## 代码外壳配色修订（1.5.3）

代码外框与分隔线使用 `--main-border-color`，顶栏使用 `--main-bg-color`，原生工具按钮底色使用 `--muted-bg-color`。变量缺失时提供浅色默认值；深浅模式跟随子比主题。此次不覆盖代码正文的语法颜色，不修改工具图标、事件、悬停对应色或双向滚动。文章不需要添加样式或脚本。

## 启用与插入

1. 在 **Mao TK Toolkit → 文章 UI 组件** 中开启需要的组件。四项独立开关，未设置时默认启用，明确关闭时保持，沿用 Toolkit 的权限、nonce、后台保存和状态反馈。
2. 在普通文章的 Gutenberg 组区块“额外 CSS 类”中填写 `maotk-ui-kit`。只将需要增强的内容放入该组，避免将付费、下载、评论或商品界面包入。
3. 按下表添加组件外层组类名，使用主题原生区块或短代码。没有明确包装的文章保持原貌。文章中的 style/script 或旧链接预览标记须先按下述迁移流程处理。

| 组件 | 外层类／内容 | 插入示例 | 原生依赖 |
| --- | --- | --- | --- |
| 代码 | `mtk-code-wrap` + 原生 `zibllblock/enlighter` | [普通代码](examples/code.blocks.html)、[宽长代码](examples/code-wide.blocks.html) | 主题的 Enlighter、三个工具及语法配色 |
| 链接 | 根组内普通 HTTP/HTTPS 链接；`mtk-link-demo` 用于定稿下划线和文字动效 | [链接示例](examples/link-preview.blocks.html) | 原链接点击、主题 golink；复制需要浏览器剪贴板能力 |
| TAB | `mtk-native-tabs` + 原生 `.post-tab.nav-top` | [TAB B](examples/tabs.blocks.html) | 主题 TAB 事件、状态和横向滚动 |
| 文章卡片 | `mtk-native-postsbox` + `[postsbox post_id="实际文章编号"]` | [文章卡片](examples/postsbox.blocks.html) | 原生 postsbox 短代码、真实文章和主题图标 |

`examples/postsbox.blocks.html` 使用醒目的 `{{POST_ID}}` 占位符；插入前必须替换为本站真实文章编号，插件不设置演示文章 31 等默认值。示例是待在目标编辑器验证的插入材料，不会自动写入或发布文章。代码语言暂沿用封板 generic 保存格式，其他语言先由当前主题编辑器核实。

链接文字放在 `.mtk-link-label` 内；示例内保留用户链环 SVG。需要图片时自行填写 `data-link-preview-image="实际图片 URL"`，没有图片保持空值；可用 `data-link-preview-title` 指定标题。插件只使用明确配置的图片，不爬取页面或调用截图服务。链接预览浮层由插件提供，**不要再次插入旧 `card.html` 或旧 `link.js`**。

## 加载与作用域

- 普通文章单页、当前文章与查询文章ID一致（支持循环外正文渲染）、Zibll 父主题（允许子主题）、无密码文章。已发布文章可用；未发布文章仅允许有该文章编辑权限的 WordPress 原生预览。后台、feed、REST、归档、商品详情跳过。
- 新组件需要 **WordPress 6.2+ 的 `WP_HTML_Tag_Processor`**。缺失时暂停新组件并保留正文，Toolkit 既有功能仍遵循原最低版本声明。PHP 设计下限保持 7.4，实际本地检查使用 PHP 8.5.12；没有全版本兼容认证。
- 头部扫描存储正文中的明确包装及组件标记，按需 enqueue 组件 CSS；只有链接／TAB 需要共享展示适配 JS。CSS 和 JS 均以 Toolkit 版本控制缓存，固定句柄去重。
- `the_content` 优先级 99 仅在显示结果根节点补充 `data-mao-tk-ui-code`、`-link-preview`、`-tabs`、`-postsbox` 属性。CSS 同时要求原根、启用属性及对应组件包装。不会保存这些属性，也不解析／改写未知 Gutenberg 序列化。
- 无根组不接入。若自定义短代码在渲染时才生成整组根标记，头部无法发现，本版保守跳过；将明确的组包装放到存储正文中。
- 代码、卡片只覆盖 CSS，不拷贝 Enlighter/TAB/业务库，不猜主题依赖句柄。TAB 仅一次包装原链接子节点，保留链接对象、属性、事件和原生状态。
- 链接浮层挂在 body，仅自有 `data-mao-tk-ui-owned="1"` 浮层受到插件 CSS 影响。已知下载、收银、原生 TAB／代码／文章卡片、dialog、Bootstrap `.modal`、指定关闭和非网页协议跳过。主题新增业务 DOM 仍须实站复核。

## 初始化与清理

脚本仅在 DOM ready 初始化一次，没有全页常驻 MutationObserver。提供：

```javascript
// container 必须是已经按 PHP 呈现规则标记的正文 DOM，不接受 HTML 字符串。
window.MaoTKArticleUI?.init(container);
// 在宿主移除／替换正文之前清理自有浮层、监听器、计时器及新加的 TAB 标签。
window.MaoTKArticleUI?.destroy(container);
```

也可派发 `mao-tk-toolkit:article-ui:init`，`detail.container` 为正文 DOM。重复初始化不重复浮层／标签，pagehide 清理，bfcache pageshow 重新初始化。旧 `data-mtk-link-ready="1"` 和旧内嵌浮层归原脚本所有，本版不抢占。新浮层不使用旧文章全量演示 JS。

**没有核实目标主题 PJAX／局部刷新生命周期。** 当前不盲接一个猜测事件；主题测试站核实后，在已存在的成功插入／移除回调中调用上述 API。局部跳转到此前未加载组件资源的文章还需宿主资源加载支持；不能把显式 init API 当成已自动解决所有 PJAX 场景。

无脚本或初始化失败时保留可读正文、原生链接及主题内容；不隐藏正文等待增强，不负责主题原生功能的无脚本补全。

## 迁移、去重与回退

本版没有自动迁移、扫描数据库或删除文章代码的入口。

1. 导出当前实际文章的原始正文、版本和必要元数据，建立可还原备份；对比本交接快照，确认最新来源。
2. 盘点每段内嵌 style/script 的组件归属。综合演示混有折叠、按钮、轮播等逻辑，不能全删或再次整份加载。
3. 先在独立草稿中，仅移除本次已迁组件对应资源及旧浮层／初始化标记；保留其他组件、正文、标题、SEO、下载、图片、ALT、说明与原生目录。
4. 保留原组包装／原生区块，在插件中启用所需组件，检查网络资源各一次、链接原属性、交互及编辑器重开保存，然后读回对比正文。
5. 逐篇记录后才按明确授权扩大范围。教程 31 和正式文章未由本版本修改或发布。

安全避让：存储或最终正文含任何真实 `<style>`／`<script>` 时，本版不添加新增强，避免未知混合脚本双绑。旧预览浮层／ready 标记另阻止链接接入，其他三项可独立适配。这会使含无关内嵌脚本的文章也暂不增强，属于保守限制。不要为绕过检测而盲删代码。

回退时先关闭对应四个模块；正文存储、文章属性、封面和 SEO 均保留。若已经手动迁移文章，恢复对应文章备份才能恢复原内嵌效果。降回 Toolkit 1.3.5 时新增 option 中的模块布尔项可以保留，旧版忽略；不要删除整个 Toolkit 配置。缓存按测试站原策略刷新。

## 来源与差异

| 内容 | 运行位置 | 相对封板的改动 |
| --- | --- | --- |
| 四个 CSS | `assets/article-ui/<component>/` | 增加逐组件启用属性；聚焦改为单层内缘反馈 |
| 链接 CSS/JS | `assets/article-ui/link-preview/`、`assets/article-ui/article-ui.js` | 自有浮层隔离；业务动作过滤、幂等、清理、异步复制竞态保护、图片失败回退、Unicode golink 解码；动效时长和图形保留 |
| TAB | 共享适配 JS + `tabs/tabs.css` | 仅补展示 span；限定 nav-top，不加入切换代码 |
| 卡片 | `postsbox/postsbox.css` | 显式 `overflow:hidden` 保障两／三行省略不依赖主题工具类，避免长标题溢出到信息区 |
| 本地图标 | `link-preview/assets/icons/`、`originals/` | 原样保留 SVG 和 Lucide ISC LICENSE；浮层模板保留原图形 |

来源是用户提供的 MaoTK 2026-10-30 交接 ZIP。四个封板原件和截图保存在项目参考目录，未修改；整体源码授权依用户持有材料，本版本不额外编造整体许可证。Lucide 图标许可随资源保留。没有新增远程字体、图标 CDN 或网络服务。

第二批商品宽度草稿、付费美化均未纳入；折叠／阅读排版／图片已按下节独立接入。正式主题安装、原生复制／新窗口、编辑器校验及当前主题局部刷新需要测试站另验收；本地函数替身检查不能替代这些结果。

## 正文阅读与折叠（1.5.4）

在根组中同时填写 `maotk-ui-kit mtk-article-reading`。独立模块 `article-ui-reading` 未设置时默认开启，可在文章 UI 设置中关闭；没有显式类名的其他文章保持。仅为标记的正文加载 reading.css/reading.js，其他封板组件资源不变。

原生 Image 块获得10px圆角和细边框，直属段落和列表使用紧凑间距。文章标题/H2/H3和主题目录不增加覆盖；折叠问题内部的 h3.mtk-faq-heading 只服务已确认的组件排版。

FAQ结构为 `.mtk-faqs > section.mtk-faq-item[data-mtk-faq]`，每项保留 heading、button.mtk-faq-trigger、图标与 `.mtk-faq-panel > .mtk-faq-answer > .mtk-faq-content`。按钮/面板使用唯一ID和aria关联，无JS时保持全文可见。普通操作280ms连续网格过渡，图标中心旋转；减少动态效果时取消动画。每组只展开一个问题，目录锚点在主题滚动前展开目标，不接管滚动。

变量复制使用 `button[data-mtk-copy]`、内部 `[data-mtk-copy-label]` 和根内独立状态 `[data-mtk-copy-status]`，不复制主题图标。复制失败提示手工选取，成功反馈不应当作系统剪贴板内容已经独立验收。

`MaoTKArticleReading.init(container)` / `destroy(container)`供显式局部生命周期接入；支持现有 article-ui:init 事件。不会自动解决尚未核对的主题PJAX资源装载。

迁移教程31时逐项移除旧内嵌文章资源，保留下载块、原图和SEO。禁止在保留旧脚本的文章里重复绑定。

1.5.7代码维护约定：插件只管外壳和滚动。顶栏按主题中性色混合，与原生代码区区分；代码文本和token不设置颜色。提示词、邮件、路径与目录树用原生raw，程序按真实语言，data-enlighter-theme留空继承主题。浅/深语法主题由子比设置和切换逻辑负责，不把语法主题误作品牌主色。

1.5.8：标记根组内普通 p/li/td/th 的文字链接自动获得 mtk-inline-link 与既定文字/底线动效，不必逐篇塞入图标SVG；mtk-link-demo片段继续兼容。装饰仅为运行时DOM，不写回post_content；移除时还原原子节点，保留anchor及原事件。未知/商业按钮和图片入口保持不变。
