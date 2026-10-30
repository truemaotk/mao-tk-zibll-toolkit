<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

final class ThemeCompatibility
{
    public function inspect(): array
    {
        $template = function_exists('get_template') ? (string) get_template() : '';
        $stylesheet = function_exists('get_stylesheet') ? (string) get_stylesheet() : '';
        $version = '';
        if ($template !== '' && function_exists('wp_get_theme')) {
            $theme = wp_get_theme($template);
            if (is_object($theme) && method_exists($theme, 'get')) {
                $version = (string) $theme->get('Version');
            }
        }

        return array(
            'is_zibll' => $template === 'zibll',
            'template' => $template,
            'stylesheet' => $stylesheet,
            'is_child' => $template !== '' && $stylesheet !== '' && $template !== $stylesheet,
            'version' => $version,
            // Optional observations only; V1 does not call either framework.
            'csf_available' => class_exists('CSF', false),
            'widget_available' => class_exists('Zib_CFSwidget', false),
        );
    }
}
