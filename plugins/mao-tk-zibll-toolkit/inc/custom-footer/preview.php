<?php defined('ABSPATH') || exit;
$base=MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/';
$width=function_exists('zib_get_page_max_width')?zib_get_page_max_width():'1200px';
if (!is_string($width)||!preg_match('/^[1-9][0-9]{2,4}px$/D',$width)) $width='1200px';
$color='#f04494';
if (function_exists('_pz')) {
    $custom=_pz('theme_skin_custom');$preset=_pz('theme_skin');
    $candidate=is_string($custom)&&$custom!==''?$custom:(is_string($preset)?'#'.ltrim($preset,'#'):'');
    $color=sanitize_hex_color($candidate)?:$color;
}
?>
<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="<?php echo esc_url(home_url('/')); ?>">
<link rel="stylesheet" href="<?php echo esc_url($base.'preview.css?ver='.MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>">
<?php foreach (array('footer','social','responsive','theme','layout','legal','social-controls') as $sheet): ?><link rel="stylesheet" href="<?php echo esc_url($base.'css/'.$sheet.'.css?ver='.MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>"><?php endforeach; ?>
<style>body{--mian-max-width:<?php echo esc_html($width); ?>;--focus-color:<?php echo esc_html($color); ?>}</style>
<script src="<?php echo esc_url(MAO_TK_ZIBLL_TOOLKIT_URL.'assets/js/disclosure-motion.js?ver='.MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>" defer></script>
<script src="<?php echo esc_url($base.'js/footer.js?ver='.MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>" defer></script><script src="<?php echo esc_url($base.'preview.js?ver='.MAO_TK_ZIBLL_TOOLKIT_VERSION); ?>" defer></script>
</head><body><?php (new \MaoTK\ZibllToolkit\CustomFooter\Renderer($base))->render($value); ?></body></html>
