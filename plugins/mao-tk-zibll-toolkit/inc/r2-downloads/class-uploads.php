<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Uploads
{
    public const PART_SIZE = 32 * 1024 * 1024;
    public const MAX_SIZE = 30 * 1024 * 1024 * 1024;

    public static function start(Storage $storage, string $filename, string $bytes): array
    {
        if (!preg_match('/\A[1-9][0-9]{0,10}\z/', $bytes) || (int) $bytes > self::MAX_SIZE) {
            throw new \InvalidArgumentException('请选择大于 0 字节、最大 30 GiB 的文件。');
        }
        $name = sanitize_file_name($filename);
        if (!Storage::valid_key($name) || strpos($name, '/') !== false || strlen($name) > 240) {
            throw new \InvalidArgumentException('文件名无效或过长，请修改后重试。');
        }
        $key = Storage::TOOLKIT_PREFIX . wp_generate_uuid4() . '/' . $name;
        $upload_id = $storage->create($key);
        $ticket = self::encode(wp_json_encode(array(
            'user' => get_current_user_id(),
            'destination' => $storage->destination(),
            'key' => $key,
            'upload' => $upload_id,
            'size' => (int) $bytes,
            'expires' => time() + DAY_IN_SECONDS,
        )));
        return array('ticket' => $ticket . '.' . self::ticket_signature($ticket));
    }

    public static function session(Storage $storage, string $ticket): array
    {
        if (strlen($ticket) > 6000 || !preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{64})\z/', $ticket, $parts)
            || !hash_equals(self::ticket_signature($parts[1]), $parts[2])) {
            throw new \InvalidArgumentException('上传会话无效，请重新选择文件。');
        }
        $job = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        if (!is_array($job) || $job['user'] !== get_current_user_id() || $job['destination'] !== $storage->destination()
            || $job['expires'] <= time()) {
            throw new \InvalidArgumentException('上传会话已过期，或与当前账号或存储配置不符。');
        }
        return $job;
    }

    public static function part(Storage $storage, array $job, string $number): string
    {
        if (!preg_match('/\A[1-9][0-9]{0,2}\z/', $number) || (int) $number > self::part_count($job)) {
            throw new \InvalidArgumentException('分片编号无效。');
        }
        return $storage->part_url($job['key'], $job['upload'], (int) $number);
    }

    public static function finish(Storage $storage, array $job): array
    {
        $parts = $storage->parts($job['key'], $job['upload']);
        if ($parts !== null) {
            $count = self::part_count($job);
            if (count($parts) !== $count) {
                throw new \RuntimeException('R2 收到的分片还不完整。');
            }
            for ($number = 1; $number <= $count; ++$number) {
                $size = min(self::PART_SIZE, $job['size'] - ($number - 1) * self::PART_SIZE);
                if (($parts[$number]['size'] ?? null) !== $size) {
                    throw new \RuntimeException('R2 收到的分片大小与所选文件不符。');
                }
            }
            ksort($parts, SORT_NUMERIC);
            $storage->complete($job['key'], $job['upload'], $parts);
        }
        if ($storage->size($job['key']) !== $job['size']) {
            throw new \RuntimeException('R2 未确认最终文件大小。请重试确认。');
        }
        return array('key' => $job['key']);
    }

    private static function part_count(array $job): int
    {
        return intdiv($job['size'] - 1, self::PART_SIZE) + 1;
    }

    private static function ticket_signature(string $ticket): string
    {
        return hash_hmac('sha256', 'maotk-r2-upload-v1.' . $ticket, wp_salt('auth'));
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
