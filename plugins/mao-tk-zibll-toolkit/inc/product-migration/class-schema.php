<?php
namespace MaoTK\ZibllToolkit\ProductMigration;
defined('ABSPATH') || exit;

/** Contract audited against the user's Zibll V9.2 (1651) source. See data-migration/products/README.md. */
final class Schema
{
    public const CONTRACT='zibll-shop-9.2-v1';
    public const TAXONOMIES=array('shop_cat','shop_tag');
    private const CONFIG_KEYS=array('desc','cover_images','cover_videos','params','main_image','pay_modo','start_price','product_options','rebate','limit_buy','stock_type','stock_all','stock_opts','user_required','shipping_type','guest_buy_s','email_fill','shipping_delivery_desc','auto_delivery','shipping_fee_opt','after_sale_opt','service','single_tabs','content_layout','content_show_bg');
    private const CAT_KEYS=array('limit_buy','shipping_fee_opt','guest_buy_s','email_fill','after_sale_opt','rebate','service','single_tabs','content_after','content_layout','content_show_bg','list_style_style');

    public static function errors(): array
    {
        $out=array();$theme=wp_get_theme(get_template());
        if(get_template()!=='zibll'||!preg_match('/^9\.2(?:\.|$)/',(string)$theme->get('Version')))$out[]='商品迁移目前仅适配已核实的 Zibll 9.2（支持其子主题）。';
        if(!post_type_exists('shop_product')||!taxonomy_exists('shop_cat')||!taxonomy_exists('shop_tag'))$out[]='请先启用子比原生商城，商品类型或分类尚未注册。';
        foreach(array('zib_shop_get_product_config','zib_shop_save_product_config') as $function)if(!function_exists($function))$out[]='原生商品配置接口不可用：'.$function;
        if(!has_action('updated_post_meta','zib_shop_update_postmeta_add_action')||!has_action('added_post_meta','zib_shop_update_postmeta_add_action'))$out[]='子比商品价格同步钩子未启用，已停止迁移。';
        return $out;
    }
    public static function available(): void
    {
        $errors=self::errors();if($errors)throw new \RuntimeException(implode(' ',$errors));
    }
    private static function fields($value,array $keys,string $label): array
    {
        if(!is_array($value))throw new \RuntimeException($label.'不是数组。');
        Package::value($value);
        foreach($value as $key=>$v)if(!is_string($key)||!in_array($key,$keys,true))throw new \RuntimeException($label.'出现未适配字段：'.(is_scalar($key)?(string)$key:'无效字段'));
        return $value;
    }
    private static function number($value,string $label,bool $integer=false): void
    {
        if(!is_scalar($value)||is_bool($value)||!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>1000000000000||($integer&&(float)$value!==(float)(int)$value))throw new \RuntimeException($label.'数值无效。');
    }
    private static function structure(array $c): void
    {
        foreach(array('desc','main_image','shipping_delivery_desc','content_layout','content_show_bg','guest_buy_s','email_fill','list_style_style','content_after','class','priority','is_important') as $key)if(isset($c[$key])&&!is_scalar($c[$key]))throw new \RuntimeException($key.'必须是文本或标记值。');
        foreach(array('guest_buy_s'=>array('','on','off'),'email_fill'=>array('','required','fill','off')) as $key=>$values)if(isset($c[$key])&&!in_array((string)$c[$key],$values,true))throw new \RuntimeException($key.'选项不受支持。');
        foreach(array('cover_videos'=>array('url'),'params'=>array('name','value'),'user_required'=>array('name','desc'),'service'=>array('name','desc','image')) as $key=>$fields){
            if(empty($c[$key]))continue;if(!is_array($c[$key])||count($c[$key])>200)throw new \RuntimeException($key.'列表无效。');
            foreach($c[$key] as $row){$row=self::fields($row,$fields,$key);foreach($row as $v)if(!is_scalar($v)&&$v!==null)throw new \RuntimeException($key.'列表值无效。');}
        }
        foreach(array('limit_buy','rebate','shipping_fee_opt','after_sale_opt','single_tabs') as $key)if(!empty($c[$key])&&!is_array($c[$key]))throw new \RuntimeException($key.'配置必须为数组。');
        if(!empty($c['single_tabs'])){
            $tabs=self::fields($c['single_tabs'],array('type','tabs'),'详情栏目');
            if(isset($tabs['type'])&&!in_array($tabs['type'],array('','disable','custom'),true))throw new \RuntimeException('详情栏目类型无效。');
            if(!empty($tabs['tabs'])){if(!is_array($tabs['tabs'])||count($tabs['tabs'])>100)throw new \RuntimeException('详情栏目数量无效。');foreach($tabs['tabs'] as $row){$row=self::fields($row,array('title','content'),'详情栏目');foreach($row as $v)if(!is_string($v))throw new \RuntimeException('详情栏目文字无效。');}}
        }
        foreach(array('limit_buy'=>array('type','all','auth','vip_1','vip_2','desc'),'rebate'=>array('type','all_ratio','vip_1_ratio','vip_2_ratio','all_fixed','vip_1_fixed','vip_2_fixed')) as $key=>$fields){
            if(empty($c[$key]))continue;$group=self::fields($c[$key],$fields,$key);foreach($group as $field=>$v){if(!is_scalar($v)&&$v!==null)throw new \RuntimeException($key.'取值无效。');if($field!=='type'&&$field!=='desc'&&$v!=='')self::number($v,$key);}
        }
        if(!empty($c['shipping_fee_opt'])){$s=self::fields($c['shipping_fee_opt'],array('type','fixed_fee','amount_fee','desc'),'运费');foreach(array('type','desc') as $key)if(isset($s[$key])&&!is_string($s[$key]))throw new \RuntimeException('运费文字无效。');if(isset($s['fixed_fee'])&&$s['fixed_fee']!=='')self::number($s['fixed_fee'],'固定运费');if(!empty($s['amount_fee'])){foreach(self::fields($s['amount_fee'],array('fee','free_amount'),'满额运费') as $v)if($v!=='')self::number($v,'满额运费');}}
        if(!empty($c['after_sale_opt'])){$a=self::fields($c['after_sale_opt'],array('type','opt'),'售后');if(isset($a['type'])&&!in_array($a['type'],array('','custom'),true))throw new \RuntimeException('售后类型无效。');if(!empty($a['opt']))foreach(self::fields($a['opt'],array('refund','refund_max_day','refund_return','refund_return_max_day','replacement','replacement_max_day','warranty','warranty_max_day','insured_price','insured_price_max_day','desc'),'售后明细') as $v)if(!is_scalar($v)&&$v!==null)throw new \RuntimeException('售后取值无效。');}
    }
    public static function combinations(array $options): array
    {
        $out=array('');
        foreach($options as $g=>$group){$next=array();foreach($out as $prefix)foreach($group['opts'] as $v=>$option){$next[]=$prefix.'|'.$g.'_'.$v;if(count($next)>512)throw new \RuntimeException('规格组合超过 512 个，请拆分商品。');}$out=$next;}
        return $options?$out:array('0');
    }
    public static function config($input): array
    {
        $c=self::fields($input,self::CONFIG_KEYS,'商品配置');
        self::structure($c);
        if(!array_key_exists('start_price',$c))throw new \RuntimeException('商品缺少起始价格，不能按普通文章迁移。');
        self::number($c['start_price'],'起始价格');if((float)$c['start_price']<0)throw new \RuntimeException('起始价格不能小于 0。');
        foreach(array('pay_modo'=>array('0','points'),'stock_type'=>array('all','opts'),'shipping_type'=>array('express','auto','manual'),'content_layout'=>array('','full','box','side'),'content_show_bg'=>array('','on','off')) as $key=>$values)if(isset($c[$key])&&(!is_scalar($c[$key])||!in_array((string)$c[$key],$values,true)))throw new \RuntimeException($key.'选项无效。');
        $opts=$c['product_options']??array();if(!is_array($opts)||count($opts)>10)throw new \RuntimeException('规格分组无效或超过 10 组。');$minimum=(float)$c['start_price'];
        foreach($opts as $g=>$group){
            if(!ctype_digit((string)$g)||!is_array($group)||!is_string($group['name']??null)||$group['name']===''||!is_array($group['opts']??null)||!$group['opts']||count($group['opts'])>100)throw new \RuntimeException('规格名称、键值或选项无效。');
            self::fields($group,array('name','view_mode','opts'),'规格分组');if(isset($group['view_mode'])&&!in_array($group['view_mode'],array('list','img'),true))throw new \RuntimeException('规格显示类型无效。');$prices=array();
            foreach($group['opts'] as $v=>$option){
                if(!ctype_digit((string)$v))throw new \RuntimeException('规格值键必须为原生数字键。');$option=self::fields($option,array('name','price_change','image'),'规格值');
                if(!is_string($option['name']??null)||$option['name']===''||(isset($option['image'])&&!is_string($option['image'])))throw new \RuntimeException('规格值名称或图片无效。');
                self::number($option['price_change']??0,'规格差价');$prices[]=(float)($option['price_change']??0);
            }$minimum+=min($prices);
        }
        if($minimum<0)throw new \RuntimeException('规格组合计算后的售价小于 0。');
        $combos=self::combinations($opts);
        if(isset($c['stock_all'])){self::number($c['stock_all'],'总库存',true);if((int)$c['stock_all']<-1)throw new \RuntimeException('库存只允许 -1 或非负整数。');}
        if(isset($c['stock_opts'])){if(!is_array($c['stock_opts']))throw new \RuntimeException('规格库存无效。');foreach($c['stock_opts'] as $k=>$v){if(!in_array((string)$k,$combos,true))throw new \RuntimeException('库存键与商品规格不一致，请在源站保存并刷新后修正。');self::number($v,'规格库存',true);if((int)$v<-1)throw new \RuntimeException('规格库存无效。');}}
        if(isset($c['auto_delivery'])){
            $a=self::fields($c['auto_delivery'],array('type','fixed_content','invit_code_mode','auto_stock','invit_code_key','invit_code_auto_add_opts','card_pass_key','opts','extra_delivery_content'),'自动发货');
            foreach(array('type','fixed_content','invit_code_mode','auto_stock','invit_code_key','card_pass_key','extra_delivery_content') as $key)if(isset($a[$key])&&!is_scalar($a[$key]))throw new \RuntimeException('自动发货文字或开关无效。');
            if(isset($a['type'])&&!in_array($a['type'],array('fixed','invit_code','card_pass','opts'),true))throw new \RuntimeException('自动发货类型无效。');
            if(isset($a['opts'])){if(!is_array($a['opts']))throw new \RuntimeException('按规格发货配置无效。');foreach($a['opts'] as $k=>$v){if(!in_array((string)$k,$combos,true))throw new \RuntimeException('发货键与商品规格不一致。');self::fields($v,array('opts_type','fixed_content','opts_invit_code_mode','auto_stock','invit_code_key','invit_code_auto_add_opts','card_pass_key'),'规格发货');}}
        }
        foreach(array('desc','main_image','shipping_delivery_desc','content_layout','content_show_bg') as $k)if(isset($c[$k])&&!is_scalar($c[$k]))throw new \RuntimeException($k.'字段格式无效。');
        if(isset($c['cover_images'])&&$c['cover_images']!==false&&(!is_string($c['cover_images'])||!preg_match('/^(?:\d+(?:,\d+)*)?$/D',$c['cover_images'])))throw new \RuntimeException('封面图集编号无效。');
        return $c;
    }
    public static function term_config($c,string $tax): array
    {
        $c=self::fields($c,$tax==='shop_cat'?self::CAT_KEYS:array('priority','class','is_important'),'商品分类／标签配置');self::structure($c);return $c;
    }
    public static function term_conflict(array $terms,string $key): bool
    {
        while($key!==''){
            $t=$terms[$key];$existing=get_term_by('slug',$t['slug'],$t['tax']);
            if($existing){
                if((get_term_meta($existing->term_id,$t['tax'].'_config',true)?:array())!==$t['config'])return true;
                $parent=$t['parent']!==''?get_term_by('slug',$terms[$t['parent']]['slug'],$t['tax']):false;
                if((int)$existing->parent!==($parent?(int)$parent->term_id:0)||($t['parent']!==''&&!$parent))return true;
            }$key=$t['parent'];
        }return false;
    }
    public static function native(int $id): array
    {
        return self::config(zib_shop_get_product_config($id));
    }
    public static function image_ids(array $c): array
    {
        return array_values(array_filter(array_map('intval',explode(',',(string)($c['cover_images']??'')))));
    }
    public static function strings($value): array
    {
        $out=array();if(is_string($value))$out[]=$value;elseif(is_array($value))foreach($value as $v)$out=array_merge($out,self::strings($v));return $out;
    }
    /** Replaces exact URLs/known HTML media only, never arbitrary numbers such as prices or inventory. */
    public static function rewrite($value,array $urls,array $images,array $products=array())
    {
        if(is_array($value)){foreach($value as $k=>$v)$value[$k]=self::rewrite($v,$urls,$images,$products);return $value;}
        if(!is_string($value))return $value;
        return isset($urls[$value])?$urls[$value]:Markup::rewrite($value,$urls,$images,$products);
    }
    public static function rewrite_config(array $c,array $urls,array $images,array $products=array()): array
    {
        $c=self::rewrite($c,$urls,$images,$products);
        if(!empty($c['cover_images'])){$out=array();foreach(self::image_ids($c) as $id){if(!isset($images[$id]))throw new \RuntimeException('缺少封面图集映射，不能沿用源站附件 ID。');$out[]=$images[$id];}$c['cover_images']=implode(',',$out);}
        return self::config($c);
    }
    public static function save(int $id,array $c): void
    {
        // WordPress strips slashes in update_post_meta; the native wrapper does not add them.
        zib_shop_save_product_config($id,null,wp_slash($c));
        // A no-op metadata update does not fire its hook; avoid leaving a slashed native request cache.
        global $shop_product_configs;unset($shop_product_configs[$id]);
        // Zibll's added/updated_post_meta callback synchronizes its global cache and payment index.
        if(get_post_meta($id,'product_config',true)!==$c)throw new \RuntimeException('商品配置保存后不一致。');
        if((string)get_post_meta($id,'zibpay_price',true)!==(string)$c['start_price']||(string)get_post_meta($id,'zibpay_modo',true)!==(string)($c['pay_modo']??'0'))throw new \RuntimeException('原生支付价格索引未同步。');
        foreach(array_unique(array_merge(array('thumbnail','medium','large','full'),get_intermediate_image_sizes())) as $size)wp_cache_delete($id,'post_thumbnail_url_'.$size);
        clean_post_cache($id);
    }
}
