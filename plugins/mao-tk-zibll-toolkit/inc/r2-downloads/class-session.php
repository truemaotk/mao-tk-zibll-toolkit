<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Session
{
    public const STATE = 'mtk_r2_state';
    public const PROOF = 'mtk_r2_proof';
    private const COOKIE = '__Host-maotk-r2-start';
    private const HANDSHAKE_TTL = 120;

    public static function returning(): bool
    {
        return isset($_GET[self::STATE]) || isset($_GET[self::PROOF]);
    }

    public static function download_url(string $return_url, string $resource, string $key): string
    {
        if (Runtime::origin() === '' || Runtime::signing_key() === '') {
            wp_die('下载签名尚未配置，请联系管理员。', '', array('response' => 503));
        }

        $cookie = $_COOKIE[self::COOKIE] ?? '';
        $cookie = is_string($cookie) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $cookie) ? $cookie : '';
        $resource = hash('sha256', $resource . "\n" . $key);
        if (!self::returning()) {
            if ($cookie === '') {
                $cookie = Uploads::encode(random_bytes(32));
                if (!setcookie(self::COOKIE, $cookie, array(
                    'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
                ))) {
                    wp_die('暂时无法建立下载会话，请重试。', '', array('response' => 503));
                }
            }
            $state = self::sign('r2-session-start', array(
                'return' => $return_url, 'resource' => $resource, 'browser' => hash('sha256', $cookie),
                'exp' => time() + self::HANDSHAKE_TTL,
            ));
            Runtime::send_download(Runtime::origin() . '/v2/session?state=' . $state, 303);
        }

        $state_token = self::query(self::STATE);
        $state = self::verify('r2-session-start', $state_token);
        $proof = self::verify('r2-session-proof', self::query(self::PROOF));
        if ($cookie === '' || !$state || !$proof
            || ($state['browser'] ?? '') !== hash('sha256', $cookie)
            || ($state['resource'] ?? '') !== $resource
            || ($proof['state'] ?? '') !== hash('sha256', $state_token)
            || !is_string($proof['browser'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/', $proof['browser'])) {
            wp_die('下载会话已失效，请回到文章重新点击下载。', '', array('response' => 403));
        }

        $grant = self::sign('r2-session-download', array(
            'key' => $key, 'browser' => $proof['browser'], 'exp' => time() + 600,
        ));
        return Runtime::origin() . '/v2/download?token=' . $grant;
    }

    private static function query(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? wp_unslash($_GET[$key]) : '';
    }

    private static function sign(string $purpose, array $data): string
    {
        $payload = Uploads::encode(wp_json_encode($data));
        return $payload . '.' . self::signature($purpose, $payload);
    }

    private static function signature(string $purpose, string $payload): string
    {
        return Uploads::encode(hash_hmac('sha256', $purpose . '.' . $payload, Runtime::signing_key(), true));
    }

    private static function verify(string $purpose, string $token): ?array
    {
        if (strlen($token) > 8192 || !preg_match('/\A([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{43})\z/', $token, $parts)
            || !hash_equals(self::signature($purpose, $parts[1]), $parts[2])) {
            return null;
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        $now = time();
        return is_int($data['exp'] ?? null) && $data['exp'] > $now && $data['exp'] <= $now + self::HANDSHAKE_TTL + 30
            ? $data : null;
    }
}
