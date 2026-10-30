<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;
?>
<div class="mao-tk-note-palette" id="mao-tk-note-palette" role="dialog" aria-modal="false" aria-labelledby="mao-tk-note-palette-title" hidden>
    <div class="mao-tk-note-palette-head"><strong id="mao-tk-note-palette-title">选择颜色</strong><button type="button" class="mao-tk-note-text-button" data-note-color-close>完成</button></div>
    <div class="mao-tk-note-sv" data-note-sv role="slider" tabindex="0" aria-label="饱和度和明度，左右调饱和度，上下调明度" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"><span data-note-sv-handle aria-hidden="true"></span></div>
    <label class="screen-reader-text" for="mao-tk-note-hue">色相</label><input id="mao-tk-note-hue" data-note-hue class="mao-tk-note-hue" type="range" min="0" max="359" step="1" value="0">
    <div class="mao-tk-note-palette-values"><span data-note-color-value>#A54D08</span><span data-note-color-hsv></span></div>
    <div class="mao-tk-note-palette-presets" data-note-color-presets role="group" aria-label="类型默认颜色"></div>
    <p>拖动选色，方向键微调。留空或选“继承”可跟随默认。</p>
</div>
