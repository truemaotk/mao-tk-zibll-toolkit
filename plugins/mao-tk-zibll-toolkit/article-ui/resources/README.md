# 推荐与外部工具入口 · Toolkit 1.7.0

已接入用户确认的 v1.1.1。后台：Mao TK Toolkit → 文章 UI 组件 → 内容组织 → 文章 UI · 推荐与工具入口。未设置开关默认启用，明确关闭保持。

根组额外类：maotk-ui-kit mtk-article-reading mtk-legacy-resources。只在符合条件的 Zibll 文章前台加载，不在页面／归档／后台批量加载。本模块不修改存储正文。资源由插件加载，文章不写 CSS/JS。

示例见 recommendations.blocks.html、tools.blocks.html；替换示例名称与网址，推广参数原样保留。示例中的 {{tool_icon_base_url}} 替换为本站插件 assets/article-ui/resources/assets/ 的完整 URL。工具下方折叠见 tools-extra-faq.html，先用文章内唯一值替换全部 {{instance_id}}。

链接预览、复制、打开与底线沿用原链接模块；关闭链接模块时整卡仍可原生点击，无预览增强。阅读模块关闭时 FAQ 保留可读正文。启用时按既有 280ms 折叠。没有自动网页封面抓取；仅使用显式 preview image。

图标底座尺寸、图标展示尺寸和手机间距沿用基线；放大透明留白图片采用 Flex 双轴居中、禁止收缩，不改源 PNG 或其他模块资源。

原生文件下载、付费、会员、商品、标题和目录维持主题逻辑。迁移旧文章前备份并排查旧 style/script，不能直接叠加两套资源。停用只停止增强，不删除文章、配置或链接。源码校验与上线结果另记交付报告；本目录基线文档保留历史事实。
