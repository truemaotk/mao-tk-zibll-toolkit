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
$eta = '正在估算';
if ($tracked && $done >= 2 && $total > $done && $elapsed > 0) {
    $seconds = (int) ceil(($total - $done) * $elapsed / $done);
    $eta = $seconds < 60 ? '约 ' . $seconds . ' 秒' : ($seconds < 3600 ? '约 ' . (int) ceil($seconds / 60) . ' 分钟' : '约 ' . (int) ceil($seconds / 3600) . ' 小时');
}

$badge = 'neutral';
$label = '待同步';
if (!empty($bulk['interrupted'])) {
    $label = '任务已中断';
    $badge = 'warning';
} elseif ($paused) {
    $label = $pausing ? '正在暂停' : '已暂停';
    $badge = 'warning';
} elseif ($backoff) {
    $label = '等待重试';
    $badge = 'warning';
} elseif ($waiting) {
    $label = '等待后台任务';
    $badge = 'warning';
} elseif ($active) {
    $label = $scanning ? ($tracked && $done === 0 ? '等待扫描' : '扫描中') : '同步中';
} elseif (isset($bulk['queued'])) {
    $label = !$tracked ? '已排队' : ($failed ? '处理结束，有失败' : ($queued ? '同步完成' : '没有符合条件的历史图片'));
    $badge = $tracked ? ($failed ? 'warning' : 'success') : 'neutral';
}
?>
<div class="mtk-r2-media__job-line">
    <span class="mao-tk-toolkit__badge mao-tk-toolkit__badge--<?php echo esc_attr($badge); ?>">
        <?php if ($active && !$waiting && !$backoff && (!$paused || $pausing)) : ?><span class="mtk-r2-media__pulse" aria-hidden="true"></span><?php endif; ?>
        <?php echo esc_html($label); ?>
    </span>
    <?php if ($active && $managed) : ?><span class="mtk-r2-media__muted"><?php echo ($bulk['mode'] ?? '') === 'missing' ? '仅补传未同步' : '完整核对'; ?></span><?php endif; ?>
    <?php if ($active && !$paused && !$waiting && !$backoff && $tracked) : ?><span class="mtk-r2-media__muted">预计剩余 <?php echo esc_html($eta); ?></span><?php endif; ?>
</div>
<?php if ($active) : ?>
    <progress class="mtk-r2-media__progress" max="<?php echo max(1, $total); ?>" <?php if ($managed || ($tracked && ($done > 0 || $waiting))) : ?>value="<?php echo min($total, $done); ?>"<?php endif; ?> aria-label="<?php echo $scanning ? '扫描进度' : '同步进度'; ?>"></progress>
<?php endif; ?>
<?php if (isset($bulk['queued'])) : ?>
    <p class="mtk-r2-media__muted"><?php if ($managed || ($scanning && $tracked)) : ?>已扫描 <?php echo (int) $bulk['scanned']; ?> / <?php echo $total; ?> 个附件 · <?php endif; ?>已排队 <?php echo $queued; ?> 个<?php if ($tracked) : ?> · 已处理 <?php echo $processed; ?> 个<?php if ($failed) : ?> · 失败 <?php echo $failed; ?> 个<?php endif; ?><?php endif; ?></p>
<?php endif; ?>
<?php if ($waiting) : ?><p class="mtk-r2-media__muted">超过 90 秒未收到进度。若持续停在这里，请检查 WP-Cron。</p><?php endif; ?>
<?php if ($active && !$managed) : ?><p class="mtk-r2-media__muted">旧版任务将继续处理。新建的批量任务支持暂停和继续。</p><?php endif; ?>
<?php if ($pausing) : ?><p class="mtk-r2-media__muted">当前图片文件处理后即暂停。</p><?php endif; ?>
<?php if ($backoff) : ?><p class="mtk-r2-media__muted">后台将在 <?php echo esc_html(wp_date('H:i:s', $next)); ?> 后恢复处理。</p><?php endif; ?>
<?php if (!empty($last_error['time']) && (!$managed || ($last_error['job'] ?? '') === $bulk['job'])) : ?><p class="mtk-r2-media__muted">最近一次失败 <?php echo esc_html(wp_date('Y-m-d H:i', (int) $last_error['time'])); ?>，附件 #<?php echo (int) $last_error['id']; ?>。<?php echo esc_html($last_error['message']); ?></p><?php endif; ?>
