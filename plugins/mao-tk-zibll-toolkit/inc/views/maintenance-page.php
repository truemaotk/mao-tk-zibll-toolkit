<?php
namespace MaoTK\ZibllToolkit\Maintenance;
defined('ABSPATH') || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr(get_option('blog_charset', 'UTF-8')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo esc_html($settings['title'] . ' · ' . $site_name); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url($asset . 'page.css' . $version); ?>">
    <script src="<?php echo esc_url($asset . 'page.js' . $version); ?>" defer></script>
</head>
<body class="mao-tk-maintenance mao-tk-maintenance--<?php echo esc_attr($settings['background']); ?>" data-deadline="<?php echo $deadline === null ? '' : esc_attr((string) $deadline); ?>" data-server-time="<?php echo esc_attr((string) time()); ?>">
<?php if ($preview) : ?><div class="mao-tk-maintenance__preview" role="note">维护页预览 · 不会开启维护模式</div><?php endif; ?>
<main class="mao-tk-maintenance__layout">
    <header class="mao-tk-maintenance__brand">
        <?php if ($logo !== '') : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_name); ?>" width="160" height="80"><?php else : ?><span><?php echo esc_html($site_name); ?></span><?php endif; ?>
    </header>
    <section class="mao-tk-maintenance__content" aria-labelledby="mao-tk-maintenance-title">
        <p class="mao-tk-maintenance__eyebrow">WE’LL BE BACK SOON</p>
        <h1 id="mao-tk-maintenance-title"><?php echo esc_html($settings['title']); ?></h1>
        <?php if ($remaining !== null) : ?>
        <div class="mao-tk-maintenance__countdown" role="timer" aria-live="off" aria-label="距离预计恢复时间">
            <?php foreach (array('days' => array(floor($remaining / 86400), '天'), 'hours' => array(floor($remaining % 86400 / 3600), '小时'), 'minutes' => array(floor($remaining % 3600 / 60), '分钟'), 'seconds' => array($remaining % 60, '秒')) as $key => $part) : ?>
            <div><span class="mao-tk-maintenance__number" data-unit="<?php echo esc_attr($key); ?>"><?php echo esc_html(str_pad((string) $part[0], 2, '0', STR_PAD_LEFT)); ?></span><span class="mao-tk-maintenance__unit"><?php echo esc_html($part[1]); ?></span></div>
            <?php endforeach; ?>
        </div>
        <p class="mao-tk-maintenance__schedule" data-countdown-status><?php echo esc_html($remaining === 0 ? '预计恢复时间已到，维护仍在进行中。' : '预计恢复：' . wp_date('Y年n月j日 H:i', $deadline) . '（' . wp_timezone_string() . '）'); ?></p>
        <?php else : ?><p class="mao-tk-maintenance__schedule">恢复时间待确认</p><?php endif; ?>
        <div class="mao-tk-maintenance__description"><?php echo wp_kses($settings['description'], Settings::allowed_html()); ?></div>
        <?php if ($settings['backup_url'] !== '') : ?><a class="mao-tk-maintenance__backup" href="<?php echo esc_url($settings['backup_url']); ?>" rel="noopener noreferrer"><?php echo esc_html($settings['backup_label'] ?: '访问备用站点'); ?><span aria-hidden="true">↗</span></a><?php endif; ?>
    </section>
    <?php if ($remaining !== null) : ?><div class="mao-tk-maintenance__wheel" aria-hidden="true"><img src="<?php echo esc_url($asset . 'seconds-faded.svg'); ?>" width="2000" height="2000" alt="" data-seconds-wheel><div class="mao-tk-maintenance__wheel-highlight"><img src="<?php echo esc_url($asset . 'seconds-white.svg'); ?>" width="2000" height="2000" alt="" data-seconds-wheel></div></div><?php endif; ?>
    <footer class="mao-tk-maintenance__footer">
        <?php if ($settings['copyright'] !== '') : ?><span><?php echo esc_html($settings['copyright']); ?></span><?php else : ?><span>© <?php echo esc_html(wp_date('Y') . ' ' . $site_name); ?></span><?php endif; ?>
        <?php if ($settings['beian'] !== '') : ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo esc_html($settings['beian']); ?></a><?php endif; ?>
    </footer>
</main>
</body>
</html>
