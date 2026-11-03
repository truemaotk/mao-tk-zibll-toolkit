<?php
namespace MaoTK\ZibllToolkit\CustomFooter;

defined('ABSPATH') || exit;

/** Own option only; the module manager owns the front-end switch. */
final class Settings
{
    public const OPTION = 'mao_tk_toolkit_custom_footer';
    public const MODULE = 'site-custom-footer';
    public static function defaults(): array
    {
        return array(
            'about_title' => '关于我们',
            'schema_version' => 1,
            'about_description' => get_bloginfo('description'),
            'show_about' => true,
            'show_menus' => true,
            'show_follow' => true,
            'show_friends' => true,
            'show_copyright' => true,
            'show_icp' => true,
            'width_mode' => 'theme',
            'social_icon_size' => 18,
            'social_base_enabled' => true,
            'social_base_radius' => 17,
            'social_links' => array_map(static function ($icon, $label) { return array('icon'=>$icon,'label'=>$label,'url'=>'','qr_url'=>'','icon_url'=>''); }, array('wechat','whatsapp','telegram','github','mail'), array('微信','WhatsApp','Telegram','GitHub','邮箱')),
            'menu_groups' => array(
                array('title' => '热门频道', 'links' => self::placeholder_links()),
                array('title' => '支持服务', 'links' => self::placeholder_links()),
                array('title' => '帮助中心', 'links' => self::placeholder_links()),
            ),
            'social_title' => '关注我们',
            'qrcodes' => array(
                array('label' => '关注公众号', 'url' => '', 'link' => ''),
                array('label' => '加入群聊', 'url' => '', 'link' => ''),
                array('label' => '微信咨询', 'url' => '', 'link' => ''),
            ),
            'links_heading' => '友情链接：',
            'copyright' => 'Copyright © ' . date('Y') . ' ' . get_bloginfo('name'),
            'icp_label' => '',
            'icp_url' => '',
            'icp_icon_url' => '',
            'show_performance' => false,
        );
    }

    private static function placeholder_links(): array
    {
        return array();
    }

    public static function resolve(array $overrides): array
    {
        // v0.1.10 square becomes radius=0; do not retain two competing shape controls.
        if (($overrides['social_base_shape'] ?? null) === 'square') { $overrides['social_base_radius'] = 0; }
        $defaults = self::defaults();
        $resolved = $defaults;
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $overrides)) { continue; }
            if (is_string($value) && is_string($overrides[$key])) {
                if ($key === 'width_mode' && !in_array($overrides[$key], array('theme','full'), true)) { continue; }
                $resolved[$key] = in_array($key, array('logo_url', 'icp_icon_url'), true) ? self::image_url($overrides[$key]) : $overrides[$key];
            } elseif (is_int($value) && is_numeric($overrides[$key])) {
                if ($key === 'schema_version') { continue; }
                $bounds = array('social_icon_size' => array(12,32), 'social_base_radius' => array(0,24));
                $range = $bounds[$key];
                $resolved[$key] = max($range[0], min($range[1], (int) $overrides[$key]));
            } elseif (is_bool($value) && is_bool($overrides[$key])) {
                $resolved[$key] = $overrides[$key];
            } elseif ($key === 'social_links' && is_array($overrides[$key])) {
                $resolved[$key] = self::resolve_social_links($overrides[$key]);
            } elseif (in_array($key, array('menu_groups', 'qrcodes'), true) && is_array($overrides[$key])) {
                $resolved[$key] = self::resolve_rows($key, $overrides[$key]);
            }
        }
        return $resolved;
    }

    public static function image_url($url): string
    {
        if (!is_string($url)) { return ''; }
        $url = trim($url);
        if (preg_match('~\A/(?!/)[a-zA-Z0-9/_.-]+\z~', $url) && strpos($url, '..') === false) { return $url; }
        return preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    public static function link_url($url): string
    {
        if (!is_string($url)) { return ''; }
        $url = trim($url);
        if ($url === '#' || preg_match('~\A/(?!/)~', $url)) { return $url; }
        return preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private static function resolve_links(array $rows, int $limit): array
    {
        $result = array();
        foreach (array_slice($rows, 0, $limit) as $row) {
            if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['url'] ?? null)) { continue; }
            $result[] = array('label' => $row['label'], 'url' => self::link_url($row['url']));
        }
        return $result;
    }

    private static function resolve_social_links(array $rows): array
    {
        $allowed = array('wechat', 'whatsapp', 'telegram', 'github', 'mail');
        $result = array();
        $seen = array();
        foreach (array_slice($rows, 0, 10) as $row) {
            if (!is_array($row)) { continue; }
            $icon = is_string($row['icon'] ?? null) ? $row['icon'] : '';
            if (!in_array($icon, $allowed, true) || isset($seen[$icon])) { continue; }
            $label = is_string($row['label'] ?? null) && trim($row['label']) !== '' ? trim($row['label']) : $icon;
            $url = is_string($row['url'] ?? null) ? trim($row['url']) : '';
            if (stripos($url, 'mailto:') === 0) {
                if ($icon !== 'mail' || !filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL)) { $url = ''; }
            } elseif ($url !== '' && (!preg_match('~\Ahttps?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) {
                $url = '';
            }
            $seen[$icon] = true;
            $result[] = array('icon' => $icon, 'label' => $label, 'url' => $url,
                'qr_url' => self::image_url($row['qr_url'] ?? ''), 'icon_url' => self::image_url($row['icon_url'] ?? ''));
        }
        return $result;
    }

    private static function resolve_rows(string $key, array $rows): array
    {
        $result = array();
        foreach (array_slice($rows, 0, 3) as $row) {
            if (!is_array($row)) { continue; }
            if ($key === 'qrcodes') {
                $result[] = array('label' => is_string($row['label'] ?? null) ? $row['label'] : '',
                    'url' => self::image_url($row['url'] ?? ''), 'link' => self::link_url($row['link'] ?? ''));
            } else {
                $links = self::resolve_links(is_array($row['links'] ?? null) ? $row['links'] : array(), 20);
                $result[] = array('title' => is_string($row['title'] ?? null) ? $row['title'] : '', 'links' => $links);
            }
        }
        return $result;
    }

    public static function normalize(array $raw): array
    {
        if (array_diff(array_keys($raw), array_keys(self::defaults()))) { throw new \InvalidArgumentException('页脚配置包含未知字段。'); }
        foreach ($raw as $key => $value) {
            $default = self::defaults()[$key];
            if (gettype($value) !== gettype($default)) { throw new \InvalidArgumentException('页脚字段类型无效：' . $key); }
            if (is_string($value) && strlen($value) > ($key === 'about_description' ? 6000 : 2048)) { throw new \InvalidArgumentException('文字或地址过长。'); }
        }
        if (isset($raw['schema_version']) && $raw['schema_version'] !== 1) { throw new \InvalidArgumentException('页脚配置版本无效。'); }
        foreach (array('menu_groups'=>3,'qrcodes'=>3,'social_links'=>5) as $key=>$max) {
            if (!isset($raw[$key])) { continue; }
            if ($raw[$key]!==array_values($raw[$key])) { throw new \InvalidArgumentException('页脚项目必须为列表。'); }
            if (count($raw[$key])>$max) { throw new \InvalidArgumentException('页脚项目数量过多。'); }
            foreach ($raw[$key] as $row) {
                if (!is_array($row)) { throw new \InvalidArgumentException('页脚项目格式无效。'); }
                $allowed=$key==='menu_groups'?array('title','links'):($key==='qrcodes'?array('label','url','link'):array('icon','label','url','qr_url','icon_url'));
                if (array_diff(array_keys($row),$allowed)) { throw new \InvalidArgumentException('页脚项目包含未知字段。'); }
                foreach ($row as $field=>$value) {
                    if ($field==='links') {
                        if (!is_array($value)||$value!==array_values($value)||count($value)>20) { throw new \InvalidArgumentException('每列最多 20 条导航，且必须为列表。'); }
                        foreach ($value as $link) {
                            if (!is_array($link)||array_diff(array_keys($link),array('label','url'))||!is_string($link['label']??null)||!is_string($link['url']??null)||strlen($link['label'])>300||strlen($link['url'])>2048||trim($link['url'])!==self::link_url($link['url'])) { throw new \InvalidArgumentException('导航文字或链接无效。'); }
                        }
                    } elseif (!is_string($value)||strlen($value)>2048) { throw new \InvalidArgumentException('页脚文字或地址无效。'); }
                    elseif (in_array($field,array('qr_url','icon_url'),true)||($key==='qrcodes'&&$field==='url')) { if (trim($value)!==self::image_url($value)) { throw new \InvalidArgumentException('图片地址无效。'); } }
                    elseif ($field==='link' && trim($value)!==self::link_url($value)) { throw new \InvalidArgumentException('跳转地址无效。'); }
                }
            }
        }
        $value=self::resolve($raw);
        if (isset($raw['social_links'])) {
            if (count($raw['social_links'])!==count($value['social_links'])) { throw new \InvalidArgumentException('社交图标重复或类型无效。'); }
            foreach ($raw['social_links'] as $i=>$item) { if (trim($item['url']??'')!==$value['social_links'][$i]['url']) { throw new \InvalidArgumentException('社交地址须为 HTTP(S) 或有效邮箱链接。'); } }
        }
        foreach (array('icp_url','icp_icon_url') as $key) { if (isset($raw[$key])&&trim($raw[$key])!==$value[$key]) { throw new \InvalidArgumentException('备案地址或图标无效。'); } }
        return $value;
    }

    public function read(): array
    {
        $raw=get_option(self::OPTION,null);
        try { $value=$raw===null?self::defaults():self::normalize($raw);$valid=true; }
        catch (\Throwable $error) { $value=self::defaults();$valid=false; }
        return array('valid'=>$valid,'exists'=>$raw!==null,'value'=>$value,'revision'=>hash('sha256',(string)maybe_serialize($raw)));
    }

    public function save(array $raw,string $revision): array
    {
        $state=$this->read();if (!$state['valid']||!hash_equals($state['revision'],$revision)) { throw new \RuntimeException('设置已被其他页面修改，或原配置异常。请刷新核对。'); }
        $value=self::normalize($raw);$old=get_option(self::OPTION,null);
        if (!hash_equals($revision,hash('sha256',(string)maybe_serialize($old)))) { throw new \RuntimeException('设置已变化，请刷新重试。'); }
        if ($old!==$value) {
            if ($old===null) { if (!add_option(self::OPTION,$value,'',false)) { throw new \RuntimeException('设置已变化，请刷新重试。'); } }
            else {
                global $wpdb;
                $changed=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s",maybe_serialize($value),self::OPTION,maybe_serialize($old)));
                if ($changed!==1) { throw new \RuntimeException('设置已变化或无法保存，请刷新重试。'); }
                wp_cache_delete(self::OPTION,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');
            }
        }
        return $this->read();
    }

    public static function theme_preset(): array
    {
        $value=self::defaults();
        if (!function_exists('_pz')) { return $value; }
        $description=_pz('fcode_t1_code');if (is_string($description)&&trim($description)!=='') { $value['about_description']=trim(wp_strip_all_tags($description)); }
        $copyright=_pz('fcode_t2_code_2');if (is_string($copyright)&&trim($copyright)!=='') { $value['copyright']=trim(wp_strip_all_tags($copyright)); }
        $qrs=array_merge((array)_pz('footer_mini_img'),(array)_pz('footer_mini_img_2'));
        $i=0;foreach ($qrs as $qr) { if ($i>=3) { break; }if (!is_array($qr)||!self::image_url($qr['image']??'')) { continue; }$value['qrcodes'][$i++]=array('label'=>is_string($qr['text']??null)?wp_strip_all_tags($qr['text']):'','url'=>self::image_url($qr['image']),'link'=>''); }
        return self::normalize($value);
    }
}
