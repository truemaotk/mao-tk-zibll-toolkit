<?php
namespace MaoTK\ZibllToolkit\CustomFooter;

defined('ABSPATH') || exit;

final class Renderer
{
    private $assets_url;
    private $social_preview;

    public function __construct(string $assets_url, bool $social_preview = false)
    {
        $this->assets_url = rtrim($assets_url, '/') . '/';
        $this->social_preview = $social_preview;
    }

    public function render(array $overrides = array()): void
    {
        $settings = Settings::resolve($overrides);
        $settings['qrcodes']=array_values(array_filter($settings['qrcodes'],static function(array $qr): bool { return $qr['url']!==''; }));
        $settings['show_follow']=$settings['show_follow'] && count($settings['qrcodes'])>0;
        $bookmarks = $settings['show_friends'] ? Data::bookmarks() : array();
        $performance = $settings['show_performance'] ? Data::performance() : array('queries' => null, 'seconds' => null);
        $assets_url = $this->assets_url;
        $social_links = array_values(array_filter($settings['social_links'], function (array $item): bool {
            return $item['url'] !== '' || $item['qr_url'] !== '' || $this->social_preview;
        }));
        $menu_groups = $settings['show_menus'] ? array_values(array_filter($settings['menu_groups'],static function(array $group): bool { foreach($group['links'] as $link) { if($link['label']!==''&&$link['url']!=='')return true; }return false; })) : array();
        include __DIR__ . '/templates/footer.php';
    }
}
