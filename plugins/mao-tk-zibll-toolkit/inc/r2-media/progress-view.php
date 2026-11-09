<?php
namespace MaoTK\ZibllToolkit\R2Media;

defined('ABSPATH') || exit;

$active = Sync::active($bulk);
$tracked = !empty($bulk['job']);
$managed = !empty($bulk['managed']);
$paused = $controls['paused'] ?? Sync::paused($bulk);
$pausing = $controls['pausing'] ?? ($paused && Sync::in_flight());
$scanning = !empty($bulk['running']) && (!$managed || empty($bulk['queued']));
$updated = (int) ($bulk['updated'] ?? 0);
$next = (int) ($bulk['next'] ?? 0);
$backoff = $active && !$paused && $managed && $next > time() && $next - $updated >= 20;
$waiting = $active && !$paused && $updated && time() - max($updated, $next) >= 90;
$processed = (int) ($bulk['processed'] ?? 0);
$failed = (int) ($bulk['failed'] ?? 0);
$queued = (int) ($bulk['queued'] ?? 0);
$done = (int) ($scanning ? ($bulk['scanned'] ?? 0) : $processed);
$total = (int) ($scanning ? ($bulk['total'] ?? 0) : $queued);
$elapsed = $updated - (int) ($bulk['started'] ?? 0);
if ($managed) {
    $done = max(0, $bulk['scanned'] - $queued) + $processed;
    $total = $bulk['total'];
    $elapsed -= Sync::paused_seconds($bulk['job']);
}
$eta = __('正在估算', 'mao-tk-zibll-toolkit');
if ($tracked && $done >= 2 && $total > $done && $elapsed > 0) {
    $seconds = (int) ceil(($total - $done) * $elapsed / $done);
    if ($seconds < 60) {
        $eta = sprintf(/* translators: %d: number of seconds. */ __('约 %d 秒', 'mao-tk-zibll-toolkit'), $seconds);
    } elseif ($seconds < 3600) {
        $eta = sprintf(/* translators: %d: number of minutes. */ __('约 %d 分钟', 'mao-tk-zibll-toolkit'), (int) ceil($seconds / 60));
    } else {
        $eta = sprintf(/* translators: %d: number of hours. */ __('约 %d 小时', 'mao-tk-zibll-toolkit'), (int) ceil($seconds / 3600));
    }
}

$badge = 'neutral';
$label = __('待同步', 'mao-tk-zibll-toolkit');
if (!empty($bulk['interrupted'])) {
    $label = __('任务已中断', 'mao-tk-zibll-toolkit');
    $badge = 'warning';
} elseif ($paused) {
    $label = $pausing ? __('正在暂停', 'mao-tk-zibll-toolkit') : __('已暂停', 'mao-tk-zibll-toolkit');
    $badge = 'warning';
} elseif ($backoff) {
    $label = __('等待重试', 'mao-tk-zibll-toolkit');
    $badge = 'warning';
} elseif ($waiting) {
    $label = __('等待后台任务', 'mao-tk-zibll-toolkit');
    $badge = 'warning';
} elseif ($active) {
    $label = $scanning ? ($tracked && $done === 0 ? __('等待扫描', 'mao-tk-zibll-toolkit') : __('扫描中', 'mao-tk-zibll-toolkit')) : __('同步中', 'mao-tk-zibll-toolkit');
} elseif (isset($bulk['queued'])) {
    $label = !$tracked ? __('已排队', 'mao-tk-zibll-toolkit') : ($failed ? __('处理结束，有失败', 'mao-tk-zibll-toolkit') : ($queued ? __('同步完成', 'mao-tk-zibll-toolkit') : __('没有符合条件的历史图片', 'mao-tk-zibll-toolkit')));
    $badge = $tracked ? ($failed ? 'warning' : 'success') : 'neutral';
}
?>
<div class="mtk-r2-media__job-line">
    <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($badge); ?>">
        <?php if ($active && !$waiting && !$backoff && (!$paused || $pausing)) : ?><span class="mtk-r2-media__pulse" aria-hidden="true"></span><?php endif; ?>
        <?php echo esc_html($label); ?>
    </span>
    <?php if ($active && $managed) : ?><span class="mtk-r2-media__muted"><?php echo ($bulk['mode'] ?? '') === 'missing' ? __('仅补传未同步', 'mao-tk-zibll-toolkit') : __('完整核对', 'mao-tk-zibll-toolkit'); ?></span><?php endif; ?>
    <?php if ($active && !$paused && !$waiting && !$backoff && $tracked) : ?><span class="mtk-r2-media__muted"><?php echo esc_html(sprintf(/* translators: %s: estimated remaining time, e.g. "about 5 minutes". */ __('预计剩余 %s', 'mao-tk-zibll-toolkit'), $eta)); ?></span><?php endif; ?>
</div>
<?php if ($active) : ?>
    <progress class="mtk-r2-media__progress" max="<?php echo max(1, $total); ?>" <?php if ($managed || ($tracked && ($done > 0 || $waiting))) : ?>value="<?php echo min($total, $done); ?>"<?php endif; ?> aria-label="<?php echo esc_attr($scanning ? __('扫描进度', 'mao-tk-zibll-toolkit') : __('同步进度', 'mao-tk-zibll-toolkit')); ?>"></progress>
<?php endif; ?>
<?php if (isset($bulk['queued'])) : ?>
    <?php
    $counts = array();
    if ($managed || ($scanning && $tracked)) {
        $counts[] = sprintf(/* translators: 1: number of scanned attachments, 2: total number of attachments. */ __('已扫描 %1$d / %2$d 个附件', 'mao-tk-zibll-toolkit'), (int) $bulk['scanned'], $total);
    }
    $counts[] = sprintf(/* translators: %d: number of queued attachments. */ __('已排队 %d 个', 'mao-tk-zibll-toolkit'), $queued);
    if ($tracked) {
        $counts[] = sprintf(/* translators: %d: number of processed attachments. */ __('已处理 %d 个', 'mao-tk-zibll-toolkit'), $processed);
        if ($failed) {
            $counts[] = sprintf(/* translators: %d: number of failed attachments. */ __('失败 %d 个', 'mao-tk-zibll-toolkit'), $failed);
        }
    }
    ?>
    <p class="mtk-r2-media__muted"><?php echo esc_html(implode(' · ', $counts)); ?></p>
<?php endif; ?>
<?php if ($waiting) : ?><p class="mtk-r2-media__muted"><?php esc_html_e('超过 90 秒未收到进度。若持续停在这里，请检查 WP-Cron。', 'mao-tk-zibll-toolkit'); ?></p><?php endif; ?>
<?php if ($active && !$managed) : ?><p class="mtk-r2-media__muted"><?php esc_html_e('旧版任务将继续处理。新建的批量任务支持暂停和继续。', 'mao-tk-zibll-toolkit'); ?></p><?php endif; ?>
<?php if ($pausing) : ?><p class="mtk-r2-media__muted"><?php esc_html_e('当前图片文件处理后即暂停。', 'mao-tk-zibll-toolkit'); ?></p><?php endif; ?>
<?php if ($backoff) : ?><p class="mtk-r2-media__muted"><?php echo esc_html(sprintf(/* translators: %s: time of day when processing resumes. */ __('后台将在 %s 后恢复处理。', 'mao-tk-zibll-toolkit'), wp_date('H:i:s', $next))); ?></p><?php endif; ?>
<?php if (!empty($last_error['time']) && (!$managed || ($last_error['job'] ?? '') === $bulk['job'])) : ?><p class="mtk-r2-media__muted"><?php esc_html_e('最近一次失败', 'mao-tk-zibll-toolkit'); ?> <?php echo esc_html(wp_date('Y-m-d H:i', (int) $last_error['time'])); ?><?php esc_html_e('，附件 #', 'mao-tk-zibll-toolkit'); ?><?php echo (int) $last_error['id']; ?><?php esc_html_e('。', 'mao-tk-zibll-toolkit'); ?><?php echo esc_html($last_error['message']); ?></p><?php endif; ?>
