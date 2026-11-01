<?php
namespace MaoTK\ZibllToolkit\NotFound;
defined('ABSPATH') || exit;
$preview = Runtime::preview();
status_header($preview ? 200 : 404); nocache_headers();
$state = (new Settings())->read(); $settings = $state['value'];
$feed = $state['valid'] && $settings['charity_enabled'] ? Provider::get() : array('record' => array(), 'stale' => false);
get_header();
?>
<main class="main-min-height mao-tk-nf"><div class="container mao-tk-nf__inner">
    <?php if ($preview) : ?><p class="mao-tk-nf__preview" role="status">管理员预览 · 此预览不会开启模块或改变其他页面</p><?php endif; ?>
    <header class="mao-tk-nf__header"><p class="mao-tk-nf__number" aria-hidden="true">404</p><h1><?php echo esc_html($settings['title']); ?></h1><p><?php echo esc_html($settings['description']); ?></p><a class="but jb-yellow mao-tk-nf__home" href="<?php echo esc_url(home_url('/')); ?>">返回首页</a></header>
    <?php if ($settings['charity_enabled']) : ?><section class="mao-tk-nf__panel" aria-labelledby="mao-tk-nf-charity-title"><div class="mao-tk-nf__section-head"><h2 id="mao-tk-nf-charity-title"><?php echo esc_html($settings['charity_title']); ?></h2><span>第三方公益信息</span></div>
        <?php if ($feed['record']) : $record = $feed['record']; ?>
        <div class="mao-tk-nf__record">
            <?php if ($settings['show_photo'] && $record['photo'] !== '') : ?><div class="mao-tk-nf__photo"><img src="<?php echo esc_url($record['photo']); ?>" alt="<?php echo esc_attr($record['name'] . '的公开寻亲照片'); ?>" width="120" height="150" loading="lazy" decoding="async" referrerpolicy="no-referrer"></div><?php endif; ?>
            <div class="mao-tk-nf__details"><h3><?php echo esc_html($record['name']); ?></h3><dl><?php foreach ($record['fields'] as $label => $value) : ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php endforeach; ?></dl><a class="mao-tk-nf__source" href="<?php echo esc_url($record['url']); ?>" target="_blank" rel="nofollow noopener noreferrer">查看原始寻亲信息</a></div>
        </div>
        <?php else : ?><p class="mao-tk-nf__empty">公益信息暂时无法加载，可稍后再试。</p><?php endif; ?>
        <p class="mao-tk-nf__disclaimer"><?php if ($feed['stale']) : ?>暂未获取到最新信息，以上为近期缓存。<?php endif; ?>信息由第三方接口提供，原始发布来源为<?php echo esc_html($feed['record']['source'] ?? Provider::source_label($settings['provider'])); ?>。请以来源页面的最新内容为准，联系资料也请在原页面核实。</p>
    </section><?php endif; ?>
    <?php if (Runtime::search_enabled($settings)) : ?><section class="mao-tk-nf__panel mao-tk-nf__search" aria-labelledby="mao-tk-nf-search-title"><h2 id="mao-tk-nf-search-title">搜索站内内容</h2><?php echo zib_get_main_search(); // Preserve the native theme form, actions and options. ?></section><?php endif; ?>
</div></main>
<?php get_footer();
