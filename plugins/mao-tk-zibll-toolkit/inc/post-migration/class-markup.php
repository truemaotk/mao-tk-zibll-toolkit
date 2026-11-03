<?php
namespace MaoTK\ZibllToolkit\PostMigration;
defined('ABSPATH') || exit;

/** Rewrite only known media/related-post references; ordinary code and arbitrary numeric IDs stay intact. */
final class Markup
{
    public static function rewrite(string $content, array $urls, array $images, array $posts = array()): string
    {
        $html=new \WP_HTML_Tag_Processor($content);
        while ($html->next_tag()) {
            $tag=$html->get_tag();
            foreach (array('src','data-src','data-original','href','poster') as $attr) {
                $old=$html->get_attribute($attr); if (is_string($old) && isset($urls[$old])) { $html->set_attribute($attr,$urls[$old]); }
            }
            foreach(array('srcset','data-srcset') as $attr) { $old=$html->get_attribute($attr);if(is_string($old)) { $new=preg_replace_callback('/(^|,\s*)(\S+)(\s+[^,]+)?/',static function($m)use($urls){return $m[1].($urls[$m[2]]??$m[2]).($m[3]??'');},$old);if($old!==$new)$html->set_attribute($attr,$new); } }
            $old=$html->get_attribute('style');if(is_string($old)){$new=preg_replace_callback('/url\(\s*([\x22\x27]?)([^)\x22\x27]+)\1\s*\)/',static function($m)use($urls){$url=trim($m[2]);return isset($urls[$url])?'url('.$m[1].$urls[$url].$m[1].')':$m[0];},$old);if($old!==$new)$html->set_attribute('style',$new);}
            if ($tag==='IMG') {
                $old=$html->get_attribute('class'); if(is_string($old)) { $new=preg_replace_callback('/\bwp-image-(\d+)\b/',static function($m)use($images){return isset($images[$m[1]])?'wp-image-'.$images[$m[1]]:$m[0];},$old);if($old!==$new)$html->set_attribute('class',$new); }
            }
        }
        $content=$html->get_updated_html();
        $content=preg_replace_callback('/<!--\s+wp:(image|gallery|cover|media-text)\s+(\{.*?\})(\s*\/?)\s*-->/s',static function($m)use($images,$urls){
            $data=json_decode($m[2],true);if(!is_array($data))return $m[0];$before=$data;
            foreach(array('id','mediaId') as $key)if(isset($data[$key],$images[$data[$key]]))$data[$key]=$images[$data[$key]];
            foreach(array('url','mediaUrl') as $key)if(isset($data[$key],$urls[$data[$key]]))$data[$key]=$urls[$data[$key]];
            if(isset($data['ids'])&&is_array($data['ids']))$data['ids']=array_map(static function($id)use($images){return $images[$id]??$id;},$data['ids']);
            if(isset($data['images'])&&is_array($data['images']))foreach($data['images'] as &$image){if(!is_array($image))continue;if(isset($image['id'],$images[$image['id']]))$image['id']=$images[$image['id']];if(isset($image['url'],$urls[$image['url']]))$image['url']=$urls[$image['url']];}unset($image);
            if($data===$before)return $m[0];
            return '<!-- wp:'.$m[1].' '.serialize_block_attributes($data).$m[3].' -->';
        },$content);
        // Native related article shortcode only. Unknown component IDs are intentionally preserved.
        if($posts) $content=preg_replace_callback('/\[postsbox\b[^\]]*\]/i',static function($m)use($posts){return preg_replace_callback('/\b(post_id|ids|id)\s*=\s*([\x22\x27])([\d,\s]+)\2/i',static function($a)use($posts){$value=preg_replace_callback('/\d+/',static function($n)use($posts){return (string)($posts[$n[0]]??$n[0]);},$a[3]);return $a[1].'='.$a[2].$value.$a[2];},$m[0]);},$content);
        return $content;
    }
}
