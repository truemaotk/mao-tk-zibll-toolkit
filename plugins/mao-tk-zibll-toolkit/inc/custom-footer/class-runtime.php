<?php
namespace MaoTK\ZibllToolkit\CustomFooter;
defined('ABSPATH') || exit;

/** Zibll 9.2 inner footer adapter. No theme writes or page-wide output buffering. */
final class Runtime
{
    private static $eligible=false;
    private static $armed=false;
    private static $completed=false;
    private static $native_priority=false;
    public static function register(): void
    {
        if (is_admin()) return;
        add_action('wp',array(__CLASS__,'prepare'),20);
        add_action('wp_enqueue_scripts',array(__CLASS__,'enqueue'));
        add_action('get_footer',array(__CLASS__,'arm'),0,2);
        add_action('wp_footer',array(__CLASS__,'restore'),-100);
    }
    public static function compatible($name=null): bool
    {
        if (get_template()!=='zibll'||!preg_match('/^9\.2(?:\.|$)/',(string)wp_get_theme(get_template())->get('Version'))) return false;
        $names=array();if (is_string($name)&&$name!=='') $names[]='footer-'.$name.'.php';$names[]='footer.php';
        $file=locate_template($names,false,false);
        if (!$file||!is_readable($file)||filesize($file)>262144) return false;
        $source=(string)file_get_contents($file);
        return (bool)preg_match('/do_action\(\s*[\'\"]zib_footer_conter[\'\"]\s*\)/',$source) && strpos($source,'container-footer')!==false && strpos($source,'wp_footer()')!==false;
    }
    public static function prepare(): void
    {
        self::$eligible=self::compatible() && (new Settings())->read()['valid'] && !is_feed() && !is_embed();
        if (self::$eligible) add_filter('body_class',static function($classes){$classes[]='mao-tk-custom-footer-active';return $classes;});
    }
    public static function arm($name=null,$args=array()): void
    {
        if (!self::$eligible||self::$armed||self::$completed||!self::compatible($name)) return;
        self::$armed=true;
        self::$native_priority=has_action('zib_footer_conter','zib_footer_con');
        if (self::$native_priority!==false) remove_action('zib_footer_conter','zib_footer_con',self::$native_priority);
        add_action('zib_footer_conter',array(__CLASS__,'render'),10);
        add_filter('sidebars_widgets',array(__CLASS__,'widgets'));
    }
    public static function widgets($sidebars)
    {
        if (self::$armed && is_array($sidebars)) $sidebars['all_footer']=array();
        return $sidebars;
    }
    public static function restore(): void
    {
        if (!self::$armed) return;
        remove_filter('sidebars_widgets',array(__CLASS__,'widgets'));
        remove_action('zib_footer_conter',array(__CLASS__,'render'),10);
        if (self::$native_priority!==false) add_action('zib_footer_conter','zib_footer_con',self::$native_priority);
        self::$armed=false;
        self::$completed=true;
    }
    public static function enqueue(): void
    {
        if (!self::$eligible||is_admin()) return;
        $previous=array();
        foreach (array('footer','social','responsive','theme','layout','legal','social-controls','bridge') as $sheet) {
            $handle='mao-tk-custom-footer-'.$sheet;
            wp_enqueue_style($handle,MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/css/'.$sheet.'.css',$previous,MAO_TK_ZIBLL_TOOLKIT_VERSION);
            $previous=array($handle);
        }
        wp_enqueue_script('mao-tk-toolkit-disclosure-motion',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/js/disclosure-motion.js',array(),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
        wp_enqueue_script('mao-tk-custom-footer',MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/js/footer.js',array('mao-tk-toolkit-disclosure-motion'),MAO_TK_ZIBLL_TOOLKIT_VERSION,true);
    }
    public static function render(): void
    {
        $state=(new Settings())->read();
        if (self::$armed && $state['valid']) (new Renderer(MAO_TK_ZIBLL_TOOLKIT_URL.'assets/custom-footer/'))->render($state['value']);
    }
}
