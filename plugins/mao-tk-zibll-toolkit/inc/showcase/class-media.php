<?php
namespace MaoTK\ZibllToolkit\Showcase;

defined('ABSPATH') || exit;

final class Media
{
    public const LIMIT = 524288;

    /** Import reviewed image bytes into WordPress media, without widening global upload types. */
    public static function import(\WP_REST_Request $request)
    {
        $files = $request->get_file_params(); $bytes = ''; $name = '';
        if (isset($files['icon']) && is_array($files['icon'])) {
            $file = $files['icon'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > self::LIMIT) { return self::error(__('请选择512KB以内的SVG、PNG、JPG或WebP图标。', 'mao-tk-zibll-toolkit')); }
            $bytes = (string) file_get_contents($file['tmp_name']); $name = sanitize_file_name($file['name'] ?? 'icon');
        } else {
            $url = Settings::url($request->get_param('url'));
            if (!$url) { return self::error(__('请填写完整的HTTP或HTTPS图片链接。', 'mao-tk-zibll-toolkit')); }
            // Safe HTTP API checks every redirect; no cookies, authentication or browser headers forwarded.
            $response = wp_safe_remote_get($url, array('timeout' => 12, 'redirection' => 3, 'limit_response_size' => self::LIMIT + 1, 'cookies' => array(), 'headers' => array('Accept' => 'image/svg+xml,image/png,image/jpeg,image/webp')));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return self::error(__('图片链接无法读取，请确认网址可公开访问，或改为上传文件。', 'mao-tk-zibll-toolkit')); }
            $bytes = wp_remote_retrieve_body($response); $name = sanitize_file_name(basename(wp_parse_url($url, PHP_URL_PATH) ?: 'icon'));
        }
        if ($bytes === '' || strlen($bytes) > self::LIMIT) { return self::error(__('图标为空或超过512KB。', 'mao-tk-zibll-toolkit')); }
        $mime = ''; $extension = '';
        if (preg_match('/^\s*(?:<\?xml[^>]*>\s*)?(?:<!DOCTYPE[\s\S]*?>\s*)?<svg\b/i', $bytes)) {
            $safe = self::svg($bytes); if ($safe === null) { return self::error(__('SVG包含不支持的脚本、外部引用或图形属性，请换用纯图形SVG或PNG。', 'mao-tk-zibll-toolkit')); }
            $bytes = $safe; $mime = 'image/svg+xml'; $extension = 'svg';
        } else {
            $info = @getimagesizefromstring($bytes);
            $types = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp');
            if (!$info || !isset($types[$info['mime'] ?? ''])) { return self::error(__('文件内容不是支持的图片格式。', 'mao-tk-zibll-toolkit')); }
            $mime = $info['mime']; $extension = $types[$mime];
        }
        $name = sanitize_file_name(pathinfo($name, PATHINFO_FILENAME)); $name = ($name ?: 'showcase-icon') . '-' . substr(hash('sha256', $bytes), 0, 12) . '.' . $extension;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        // SVG is sanitized above; allow its MIME only for the duration of this exact write.
        $allow = static function (array $mimes) use ($extension, $mime): array { $mimes[$extension] = $mime; return $mimes; };
        add_filter('upload_mimes', $allow);
        try { $upload = wp_upload_bits($name, null, $bytes); } finally { remove_filter('upload_mimes', $allow); }
        if (!empty($upload['error'])) { return self::error(__('图标无法写入媒体目录，请检查上传目录权限。', 'mao-tk-zibll-toolkit'), 500); }
        $id = wp_insert_attachment(array('post_title' => pathinfo($name, PATHINFO_FILENAME), 'post_mime_type' => $mime, 'post_status' => 'inherit', 'post_content' => ''), $upload['file'], 0, true);
        if (is_wp_error($id)) { wp_delete_file($upload['file']); return self::error(__('媒体记录保存失败，请重试。', 'mao-tk-zibll-toolkit'), 500); }
        if ($mime !== 'image/svg+xml') { $metadata = wp_generate_attachment_metadata($id, $upload['file']); if (is_array($metadata)) { wp_update_attachment_metadata($id, $metadata); } }
        update_post_meta($id, '_mao_tk_showcase_icon', 1);
        return rest_ensure_response(array('id' => $id, 'asset' => wp_get_attachment_url($id), 'name' => $name, 'bytes' => strlen($bytes)));
    }

    public static function svg(string $source): ?string
    {
        if (!class_exists('DOMDocument')) { return null; }
        if (stripos($source, '<!ENTITY') !== false || preg_match('/<\s*(script|foreignObject|iframe|image|animate|animateTransform|set|style)\b|\bon[a-z]+\s*=|javascript\s*:/i', $source)) { return null; }
        $source = preg_replace('/<\?xml[^>]*>|<!DOCTYPE[^>]*>/i', '', $source);
        $previous = libxml_use_internal_errors(true); $doc = new \DOMDocument();
        try { $loaded = $doc->loadXML($source, LIBXML_NONET | LIBXML_NOBLANKS); } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded || !$doc->documentElement || $doc->documentElement->localName !== 'svg' || $doc->doctype) { return null; }
        $tags = array('svg','g','path','circle','rect','line','polyline','polygon','ellipse','title','desc','defs','linearGradient','radialGradient','stop','clipPath','mask','symbol','use');
        $attrs = array('id','class','xmlns','xmlns:xlink','viewBox','width','height','x','y','x1','x2','y1','y2','cx','cy','r','rx','ry','d','points','fill','fill-rule','fill-opacity','stroke','stroke-width','stroke-linecap','stroke-linejoin','stroke-miterlimit','stroke-dasharray','stroke-dashoffset','stroke-opacity','opacity','transform','clip-path','clip-rule','mask','gradientUnits','gradientTransform','spreadMethod','offset','stop-color','stop-opacity','preserveAspectRatio','href','xlink:href','version');
        foreach ($doc->getElementsByTagName('*') as $node) {
            if (!in_array($node->localName, $tags, true)) { return null; }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $key = $attribute->nodeName; $v = $attribute->nodeValue;
                if ($key === 't' || $key === 'p-id' || strpos($key, 'data-') === 0) { $node->removeAttributeNode($attribute); continue; }
                if (!in_array($key, $attrs, true) || preg_match('/[<>]|expression\s*\(|javascript:|@import/i', $v)) { return null; }
                if (strpos($key, 'href') !== false && !preg_match('/^#[a-zA-Z0-9_-]+$/D', $v)) { return null; }
                if (stripos($v, 'url(') !== false && !preg_match('/^url\(\s*#[a-zA-Z0-9_-]+\s*\)$/D', $v)) { return null; }
            }
        }
        // Normalize harmless metadata/comments out of the emitted resource.
        $xp = new \DOMXPath($doc); foreach ($xp->query('//comment() | //processing-instruction()') as $node) { $node->parentNode->removeChild($node); }
        return $doc->saveXML($doc->documentElement) ?: null;
    }

    private static function error(string $message, int $status = 400): \WP_Error { return new \WP_Error('showcase_icon', $message, array('status' => $status)); }
}
