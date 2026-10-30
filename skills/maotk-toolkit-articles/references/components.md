# 组件调用表与保存契约

基线：Toolkit 1.5.7，2026-10-30。先核对目标版本，更新后以对应源码与实测为准。模板片段都放入同一篇文章的根组，不自带CSS/JS。

## 根组与标题

根组保存为原生 `core/group`，额外类 `maotk-ui-kit mtk-article-reading`，见 [文章外壳](../assets/article.blocks.html)。插件支持Gutenberg自动生成的内层组容器，不手写该运行时容器。

| 内容 | 保存方式 | 样式/行为归属 |
| --- | --- | --- |
| 文章H1 | 编辑器标题字段，不在正文重复 | 子比 |
| 正文H2 | `zibllblock/biaoti`，`h2.wp-block-zibllblock-biaoti.title-theme` | 子比 |
| 正文H3 | `core/heading` level 3，`h3.wp-block-heading` | 子比 |
| 目录 | 使用当前主题生成的目录、锚点和滚动 | 子比，不新增另一套 |
| 段落/列表/表格 | 原生段落/列表；表格可用明确 `mtk-reading-table` 包装 | Toolkit阅读模块只调整已标记范围 |

文章中的HTML块可承载组件结构，但不是放完整网页：不带doctype、head、body、CSS、JS或第三方库。

## 代码与可复制文案

- 模板：[code.blocks.html](../assets/code.blocks.html)。外层原生组额外类 `mtk-code-wrap`，内部原生 `zibllblock/enlighter`。
- `{{ESCAPED_CODE}}`先进行HTML文本转义；不要把真实 `<script>` 当成代码演示直接插入。提示词、路径、邮件、目录树等纯文本使用原生 `raw`（模板默认值），避免 `generic` 将 `#` 后的正文当注释。程序代码按真实语言设为 `javascript`、`json`、`powershell`、`html` 等，不把所有内容都设为generic。
- 外壳跟随主题中性色，顶栏与代码区保留明显色差；保留红黄绿圆点、三个原生工具和红/黄/绿悬停色。代码框后正文桌面20px、手机18px，由插件提供；不要在文章里再补边距。
- `data-enlighter-theme=""` 保持继承，语法颜色与浅深模式切换完全跟随子比原生Enlighter，不写死主题名、不重绘token。语法主题不是网站品牌主色；感觉不符时核对原生全局设置、区块主题字段和语言类型，而不是把全部代码涂成品牌色。
- 宽代码在框内横向滚动，长代码在框内纵向滚动；滚动条细、无三角，滑块悬停加粗。手机比例已由插件处理。

## 链接

- 普通HTTP/HTTPS链接在已包装根组内可获得插件预览。实际点击、target/rel和子比golink逻辑保留。
- [link.blocks.html](../assets/link.blocks.html)提供定稿动效：默认点状底线；悬停实线从左向右展开，仅文字轻微放大，底线及链接占位不一起放大。
- `data-link-preview-title`为可选标题，`data-link-preview-image`只填写已确认的真实封面URL；没有图片留空。插件不自动抓取页面或生成截图。
- 链接标记使用此前用户提供的链环SVG，已内置在片段中，不引入图标CDN。浮层的打开/复制图标由Toolkit本地资源提供，不在正文重做浮层。
- 不保存前台ready、owned或渲染启用属性，不把主题golink包装地址当成业务目标复制。付款、下载、TAB、代码工具等入口不当成普通链接装饰。

## TAB B：极简线条

- 模板：[tabs.blocks.html](../assets/tabs.blocks.html)。只用顶部 `nav-top`。
- 结构保留 `.post-tab`、`.post-tab-toggle`、`tab-id`、`.tab-pane`和主题active/in状态约定；每个标签与面板编号一一对应。
- 初始只选中一栏。插件补展示span，主题负责实际切换，文章不绑定第二套事件。
- 标题与正文分级，底线与标签文字同宽、间距紧凑。不要通过内联style重新调字号或加整行粗底线。
- 不为凑组件隐藏必要步骤；长教程的主流程应保持容易找到。

## 文章卡片

- 模板：[postsbox.blocks.html](../assets/postsbox.blocks.html)。用真实文章编号替换 `{{POST_ID}}`。
- 外层 `mtk-native-postsbox`，内容使用原生短代码。封面、标题、作者、评论和跳转都由主题读取。
- 不复制前台卡片HTML或手写阅读数字。示例文章31、验证文章145均不是新文章的默认关联ID。

## 五种正文提示框

模板：[note.blocks.html](../assets/note.blocks.html)。原块名 `maotk/note`，真实块提供者必须可用；它本身不要求第一批根组，但放在统一文章组中便于编排。

| 属性 | 契约 |
| --- | --- |
| `type` | `info`说明 / `primary`提示 / `success`推荐 / `warning`注意 / `danger`风险 |
| `badge`、`title` | 原标签及内部标题，保留原字段，不改成文章标题层级 |
| `showBadge`、`showTitle` | 原布尔开关，不创建第二套开关 |
| `maoTkNoteVisual` | 可选稀疏对象，仅保存这一个块需要覆盖的设置 |

配置继承：封板基线 → 通用设置 → 类型设置 → 单块显式设置。无必要不冻结所有默认值；false与未设置不同。

| 覆盖字段 | 允许值 |
| --- | --- |
| `light` / `dark` | 六位HEX颜色，例如 `#285fa2` / `#97c2ff` |
| `badgePosition` | `left` / `center` / `right` |
| `iconPosition` | `before` / `after` |
| `iconKey` | 内置 `info`、`lightbulb`、`circle-check`、`triangle-alert`、`shield-alert`、`bell`、`circle-help`、`bookmark`，或当前插件已注册的真实扩展key |
| `iconSize` | 12–28的整数，默认16 |
| `showIcon` | 布尔，隐藏无占位 |
| `titleColor` | `type` / `neutral`；当前统一风格优先type |

普通文章省略单块颜色，直接跟随后台。示例只覆盖图标：`"maoTkNoteVisual":{"iconKey":"bell","iconSize":20}`。只有用户明确要求独立配色时才增加 `light`／`dark`；它们优先于后台通用和类型颜色，不能为改善对比度自行冻结。恢复后台配色时只删除这两个键，保留其他单块配置，对象为空再删除对象。这是插件配置，不是文章CSS。图标扩展注册属于插件开发，不在文章临时插入远程SVG或脚本。

## 图片、表格与变量复制

- 图片模板：[image.blocks.html](../assets/image.blocks.html)，使用真实附件ID、完整图片URL、ALT和caption，保留原图文件；圆角10px、细边框与间距由阅读模块提供。
- 图片查看继续使用主题。不要为了边界另造lightbox、加模糊、缩小原文件或将图片转成背景图。
- 表格模板：[table.blocks.html](../assets/table.blocks.html)，使用th/td语义及 `mtk-reading-table` 包装；窄屏由插件处理，不添加固定桌面宽度。
- 变量复制模板：[copy.blocks.html](../assets/copy.blocks.html)，按钮 `data-mtk-copy` 为需要复制的纯文本；内部label与独立aria-live状态分开。只在确有可复制内容时使用。

## FAQ 折叠

- 模板：[faq.blocks.html](../assets/faq.blocks.html)。保持明确的组/项/标题按钮/面板/答案/内边距层级。
- 每项button与panel使用唯一ID，通过aria-controls / aria-labelledby关联；同页多组也不能重复ID。
- 保存时 `aria-expanded="true"`，不保存hidden、inert、ready/open等瞬时状态；这样无JS时答案仍可读，插件初始化后折叠。
- 同组只展开一个；280ms网格连续展开，箭头沿中心旋转180度，不使用加号或重新加动画代码。答案与标题留白由插件控制。
- 标题H3可参与主题目录，插件在目录滚动前展开目标；不改主题目录/滚动函数。减少动态效果由插件处理。
