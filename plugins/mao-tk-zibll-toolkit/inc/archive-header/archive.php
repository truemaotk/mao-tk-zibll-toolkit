<?php
use MaoTK\ZibllToolkit\ArchiveHeader\Runtime;
use MaoTK\ZibllToolkit\ArchiveHeader\Settings;

defined('ABSPATH') || exit;

$term = get_queried_object();
$sidebar_prefix = $term->taxonomy === 'post_tag' ? 'tag' : 'cat';
get_header();
?>
<div class="fluid-widget-wrap">
    <?php dynamic_sidebar('all_top_fluid'); ?>
    <?php dynamic_sidebar('cat_top_fluid'); ?>
</div>
<main role="main" class="container">
    <div class="content-wrap">
        <div class="content-layout">
            <?php dynamic_sidebar($sidebar_prefix . '_top_content'); ?>
            <?php Runtime::render_header($term, Settings::read()); ?>
            <?php Runtime::render_list($term); ?>
            <?php dynamic_sidebar($sidebar_prefix . '_bottom_content'); ?>
        </div>
    </div>
    <?php if ($term->taxonomy !== 'topics') { get_sidebar(); } ?>
</main>
<div class="fluid-widget-wrap">
    <?php dynamic_sidebar($sidebar_prefix . '_bottom_fluid'); ?>
    <?php dynamic_sidebar('all_bottom_fluid'); ?>
</div>
<?php get_footer(); ?>
