<?php
namespace MaoTK\ZibllToolkit\OrderQuery;
defined('ABSPATH') || exit;
get_header();
$settings = (new Settings())->read()['value'];
?>
<main class="container mao-tk-oq" id="mao-tk-order-query">
<?php while (have_posts()) : the_post(); ?>
    <section class="mao-tk-oq__panel">
        <header class="mao-tk-oq__header"><p class="mao-tk-oq__eyebrow">我的订单</p><h1><?php echo esc_html($settings['title']); ?></h1><p><?php echo esc_html($settings['description']); ?></p></header>
        <?php if (post_password_required()) : echo get_the_password_form(); ?>
        <?php elseif (!Runtime::enabled() || !Orders::available()) : ?><div class="mao-tk-oq__empty" role="status">订单查询暂不可用，请稍后再试或联系网站客服。</div>
        <?php else : ?>
        <form class="mao-tk-oq__form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-order-query>
            <input type="hidden" name="action" value="<?php echo esc_attr(Runtime::ACTION); ?>"><input type="hidden" name="page_id" value="<?php echo (int) get_the_ID(); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(Runtime::ACTION . '_' . get_the_ID())); ?>">
            <label for="mao-tk-oq-keyword">订单号、支付单号或预留联系方式</label><div class="mao-tk-oq__search"><input id="mao-tk-oq-keyword" name="keyword" type="search" autocomplete="off" maxlength="100" placeholder="输入查询内容，留空查看我的订单" aria-describedby="mao-tk-oq-scope"><button type="submit" class="but jb-yellow">查询订单</button></div>
            <p class="mao-tk-oq__scope" id="mao-tk-oq-scope"><?php echo is_user_logged_in() ? '只查询当前账号的订单。管理员在此页面也仅查看自己的订单。' : '免登录订单需在下单时的同一浏览器查询；手机号和邮箱不能代替订单归属验证。'; ?></p>
            <div class="mao-tk-oq__toolbar"><?php if ($settings['history_enabled'] && is_user_logged_in()) : ?><button type="button" data-order-mode="history">最近查询</button><button type="button" data-order-mode="clear">清空查询记录</button><?php elseif (!is_user_logged_in()) : ?><a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">登录订单账号</a><?php endif; ?><?php if ($settings['contact_url'] !== '') : ?><a href="<?php echo esc_url($settings['contact_url']); ?>" rel="nofollow noopener" target="_blank">联系客服</a><?php endif; ?></div>
            <noscript><p>请启用 JavaScript 后查询；订单详情也可在子比原生用户中心查看。</p></noscript>
        </form><p class="mao-tk-oq__feedback" role="status" aria-live="polite" data-order-feedback>准备就绪，查询不会更改订单或支付状态。</p><div class="mao-tk-oq__results" aria-busy="false" data-order-results></div>
        <?php endif; ?>
        <?php if ($settings['help'] !== '' && !post_password_required()) : ?><aside class="mao-tk-oq__help"><h2>查询说明</h2><p><?php echo nl2br(esc_html($settings['help'])); ?></p></aside><?php endif; ?>
    </section>
    <?php if (!post_password_required() && trim((string) get_the_content()) !== '') : ?><div class="mao-tk-oq__page-content"><?php the_content(); ?></div><?php endif; ?>
<?php endwhile; ?>
</main>
<?php get_footer();
