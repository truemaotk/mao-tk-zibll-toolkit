<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

/** Presentation only. No requests, account mutations or global page overlays. */
final class LoadingUI
{
    public static function enqueue(): void
    {
        wp_enqueue_style('mao-tk-toolkit-loading', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/css/loading-ui.css', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION);
        wp_enqueue_script('mao-tk-toolkit-loading', MAO_TK_ZIBLL_TOOLKIT_URL . 'assets/js/loading-ui.js', array(), MAO_TK_ZIBLL_TOOLKIT_VERSION, true);
    }

    public static function editor_placeholder(): string
    {
        return '<div class="mao-tk-skeleton mao-tk-skeleton--editor" aria-hidden="true"><div class="mao-tk-skeleton__toolbar"></div><div class="mao-tk-skeleton__columns"><div class="mao-tk-skeleton__list"></div><div class="mao-tk-skeleton__canvas"></div><div class="mao-tk-skeleton__list"></div></div></div><p class="screen-reader-text" role="status">正在加载案例库…</p>';
    }
}
