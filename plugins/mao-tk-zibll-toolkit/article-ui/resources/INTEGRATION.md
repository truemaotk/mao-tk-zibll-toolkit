# v1.1.1 接入约定

保留v1.0.0的原生整卡a和原卡片结构。href、rel和target继续由原生浏览器处理，不能嵌套a，也不能用点击代理替代。

## 标记与资源

原生Group根组额外类：
maotk-ui-kit mtk-article-reading mtk-legacy-resources

由Toolkit按组件存在性加载components.css、adapters.css，以及link-bridge.js。脚本依赖现有article-ui.js；下方FAQ仍依赖原reading.css／reading.js。不要把这些资源写入文章。

卡片内部入口是mtk-native-resource-link加mtk-link-label、mtk-link-affordance。桥接脚本只按宿主开关写入运行时data-mao-tk-ui-link标记；保存的patterns没有这类运行时标记。现有宿主控制器负责文字缩放占位、底线、焦点／悬停浮层、打开与复制。

相比v1.0.0，卡片不再使用data-link-preview="off"，并由data-link-preview-title提供正确名称。整卡点击、新标签打开及推广参数仍保留。可选previewImage只接受明确的HTTP(S)图片URL；无图时不输出该属性。

## 可变数据

推荐沿用name、subtitle、description、badge、hot、url、sponsored；新增previewTitle、previewImage。
工具沿用name、description、category、action、url、icon、invertInDark、sponsored；新增iconDisplay、previewTitle、previewImage。
iconDisplay为standard或padded预设。padded用于如FinalShell这类带大面积透明边缘的资源，不放大外框。

后续颜色／尺寸后台控制应通过插件支持的字段生成受限配置，不在文章中写style。新增自动封面要先完成LINK-COVERS.md中的服务端能力，不能靠给图片属性塞网站截图冒充自动抓取。

## 原有边界

标题与目录用Zibll原生。这里的工具卡是外部页面入口，真正的文件下载、付费、隐藏内容、商品和其他已封板模块不在范围内。

FAQ使用patterns/tools-extra-faq.html，替换唯一instance_id，保留既有280ms交互。当前源码包不是已上线模块；接入前核对真实Toolkit版本、开关及依赖。

在真实WordPress环境检查320／390／768／1024宽度、浅深模式、鼠标与键盘、完整href、打开／复制、动态添加组件和多实例FAQ。源码与最小对象检查不代替浏览器验收。

图标居中依赖本版 adapters.css，必须在 components.css 后加载。padded 图片允许超出底座内容区并被对称裁切，禁止恢复 inline-grid、自动外边距或图片 flex 收缩；不要用偏移量补偿不同工具。
