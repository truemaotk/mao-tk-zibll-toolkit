<?php
namespace MaoTK\ZibllToolkit\TopInfo;

defined('ABSPATH') || exit;

final class Renderer
{
    /** Rendering has no database access and never invokes theme hooks. */
    public function render(array $data, array $settings, bool $preview = false): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            require MAO_TK_ZIBLL_TOOLKIT_DIR . 'inc/views/top-info-banner.php';
            return (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }
}
