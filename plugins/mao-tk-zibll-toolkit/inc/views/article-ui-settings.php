<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
?>
<div class="wrap mao-tk-toolkit" id="mao-tk-toolkit">
    <h1 class="screen-reader-text">文章 UI 组件</h1>
    <header class="mao-tk-toolkit__header"><div><div class="mao-tk-toolkit__title-line"><h2>文章 UI 组件</h2><span class="mao-tk-toolkit__version">v<?php echo esc_html(MAO_TK_ZIBLL_TOOLKIT_VERSION); ?></span></div><p class="mao-tk-toolkit__intro">已确认的文章组件与阅读折叠独立启停，仅作用于明确包装的文章正文。关闭后保留正文和原生功能。</p></div></header>
    <?php $switch_notice = $notice; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/switch-feedback.php'; ?>
    <?php if (!class_exists('WP_HTML_Tag_Processor')) : ?><p class="mao-tk-toolkit__message" role="alert">文章 UI 组件需要 WordPress 6.2 或更新版本的 HTML API，当前增强暂停；原有模块不受影响。</p><?php endif; ?>
    <?php if (!$valid) : ?><p class="mao-tk-toolkit__message" role="alert">模块配置格式异常，开关写入暂停，原有配置未覆盖。</p><?php endif; ?>
    <div class="mao-tk-toolkit__layout"><div class="mao-tk-toolkit__main"><section class="mao-tk-toolkit__panel">
        <div class="mao-tk-toolkit__section-head"><div><h2>文章组件</h2><p>推荐与工具入口 v1.1.1 已接入；未设置的组件默认启用，已关闭的设置保持。</p></div><span class="mao-tk-toolkit__count"><?php echo esc_html(count(array_filter($modules, static function (array $module): bool { return $module['status'] === 'loaded'; })) . ' / ' . count($modules) . ' 运行中'); ?></span></div>
        <?php
        $groups = array('content' => array('内容与代码', array('article-ui-code', 'article-ui-link-preview')), 'organization' => array('内容组织', array('article-ui-tabs', 'article-ui-postsbox', 'article-ui-resources')), 'reading' => array('阅读体验', array('article-ui-reading')));
        foreach ($groups as $group_id => $group) :
            $group_modules = array_filter($modules, static function (array $module) use ($group): bool { return in_array($module['id'], $group[1], true); });
            if (!$group_modules) { continue; }
            Admin::disclosure_start('mao-tk-ui-' . $group_id, $group[0], count($group_modules) . ' 项组件', false, 'article-ui-modules');
            foreach ($group_modules as $module) :
            $loaded = $module['status'] === 'loaded';
            $label = $loaded ? '运行中' : ($module['status'] === 'disabled' ? '未启用' : ($module['status'] === 'blocked' ? '等待主题' : '加载失败'));
        ?>
        <article class="mao-tk-toolkit__module"><div class="mao-tk-toolkit__module-copy"><h3><?php echo esc_html($module['name']); ?></h3><p><?php echo esc_html($module['description']); ?></p><p class="mao-tk-toolkit__module-note"><?php echo esc_html($loaded ? '模块已在本次请求中加载。' : '正文及原生业务保持可用；启用后还需匹配文章包装与迁移条件。'); ?></p></div><div class="mao-tk-toolkit__module-actions"><?php $switch_module = $module; $switch_valid = $valid; $switch_return_page = ArticleUI\AdminPage::SLUG; require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/module-switch.php'; ?><span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($loaded ? 'success' : ($module['status'] === 'disabled' ? 'neutral' : 'warning')); ?>"><?php echo esc_html($label); ?></span></div></article>
        <?php endforeach; Admin::disclosure_end(); endforeach; ?>
    </section><section class="mao-tk-toolkit__panel"><?php Admin::disclosure_start('mao-tk-ui-usage', '使用方式与迁移说明', '组件包装、原生边界与旧文迁移'); ?><div class="mao-tk-disclosure__copy"><p>在 Gutenberg 的组区块“额外 CSS 类”填写 <code>maotk-ui-kit</code>，仅将需要增强的内容放入该组。</p><p>代码区块外层组填写 <code>mtk-code-wrap</code>；顶部 TAB 外层 <code>mtk-native-tabs</code>；文章短代码外层 <code>mtk-native-postsbox</code>。阅读与折叠在根组增加 <code>mtk-article-reading</code>。普通链接可使用 <code>mtk-link-demo</code> 段落范围。</p><p>推荐与外部工具入口在根组增加 <code>mtk-legacy-resources</code>，使用插件提供的推荐／工具结构；图标、边距与整卡链接沿用已确认样式。下方补充说明继续调用阅读折叠，实例 ID 必须唯一。<a href="<?php echo esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'article-ui/resources/README.md'); ?>" target="_blank" rel="noopener noreferrer">推荐与工具调用说明</a></p><p>插件只在显示时补充属性与 TAB 文字包装，不改写已存正文；不生成示例文章 ID，也不复制主题的代码工具或 TAB 事件。</p><p>已有 style/script 或旧预览浮层时先盘点、备份和逐组件去重。当前版本保守避让内嵌资源，不自动删除文章代码。</p></div><?php Admin::disclosure_end(); ?></section></div>
    <aside class="mao-tk-toolkit__aside"><section class="mao-tk-toolkit__guide"><h2>接入边界</h2><p>原生下载、付费、会员、商品、媒体和目录保持原样。阅读与折叠仅在明确标记的正文中启用；商品宽度不在本模块中接管。</p><p>本地定稿归档已校验；当前真实 WordPress、主题版本、编辑器保存及局部刷新仍需在测试站验收。</p><p><a href="<?php echo esc_url(MAO_TK_ZIBLL_TOOLKIT_URL . 'article-ui/README.md'); ?>" target="_blank" rel="noopener noreferrer">插入示例、迁移与回退说明</a></p><p><a href="<?php echo esc_url(admin_url('admin.php?page=' . ArticleNote\AdminPage::SLUG)); ?>">正文提示框设置与预览</a>：独立的原 maotk/note 区块，无需以上组件的外层包装。</p></section></aside></div>
</div>
