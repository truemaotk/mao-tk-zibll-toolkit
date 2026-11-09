<?php
namespace MaoTK\ZibllToolkit\R2Downloads;

defined('ABSPATH') || exit;

final class Storage
{
    public const TOOLKIT_PREFIX = 'toolkit/';
    private $host;
    private $bucket;
    private $access_key;
    private $secret_key;

    public static function configured(): ?self
    {
        $account = Settings::get('MAOTK_R2_ACCOUNT_ID');
        $bucket = Settings::get('MAOTK_R2_BUCKET');
        $access = Settings::get('MAOTK_R2_ACCESS_KEY_ID');
        $secret = Settings::get('MAOTK_R2_SECRET_ACCESS_KEY');
        return self::with_credentials($account, $bucket, $access, $secret);
    }

    public static function with_credentials(string $account, string $bucket, string $access, string $secret): ?self
    {
        if (
            in_array('', array($account, $bucket, $access, $secret), true) ||
            PHP_INT_SIZE < 8 ||
            !function_exists('simplexml_load_string')
        ) {
            return null;
        }
        return new self(strtolower($account) . '.r2.cloudflarestorage.com', $bucket, $access, $secret);
    }

    private function __construct(string $host, string $bucket, string $access, string $secret)
    {
        $this->host = $host;
        $this->bucket = $bucket;
        $this->access_key = $access;
        $this->secret_key = $secret;
    }

    public function destination(): string
    {
        return $this->host . '/' . $this->bucket;
    }

    public static function valid_key(string $key): bool
    {
        return $key !== '' &&
            strlen($key) <= 1024 &&
            substr($key, -1) !== '/' &&
            preg_match('//u', $key) === 1 &&
            !preg_match('/[\x00-\x1f\x7f\\\\]/', $key) &&
            !preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $key);
    }

    public static function managed_key(string $key): bool
    {
        return self::valid_key($key) &&
            preg_match(
                '~\A' .
                    self::TOOLKIT_PREFIX .
                    '[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/[^/]+\z~',
                $key
            ) === 1;
    }

    public function files(string $cursor): array
    {
        if (strlen($cursor) > 4096 || preg_match('/[^\x21-\x7e]/', $cursor)) {
            throw new \InvalidArgumentException(__('分页信息无效，请重新读取文件。', 'mao-tk-zibll-toolkit'));
        }
        $parameters = array(
            'list-type' => '2',
            'prefix' => self::TOOLKIT_PREFIX,
            'max-keys' => '25',
            'encoding-type' => 'url'
        );
        if ($cursor !== '') {
            $parameters['continuation-token'] = $cursor;
        }
        $xml = $this->xml($this->request('GET', '', $parameters, '', ''), 'ListBucketResult');
        $encoded = (string) $xml->EncodingType === 'url';
        $files = array();
        foreach ($xml->Contents as $item) {
            $key = (string) $item->Key;
            $key = $encoded ? rawurldecode($key) : $key;
            if (self::managed_key($key)) {
                $files[] = array('key' => $key);
            }
        }
        $next = (string) $xml->IsTruncated === 'true' ? (string) $xml->NextContinuationToken : '';
        if ((string) $xml->IsTruncated === 'true' && ($next === '' || $next === $cursor)) {
            throw new \RuntimeException(__('R2 返回的分页信息不完整，请重新读取文件。', 'mao-tk-zibll-toolkit'));
        }
        return array('files' => $files, 'next' => $next);
    }

    public function delete_file(string $key): void
    {
        if (!self::managed_key($key)) {
            throw new \InvalidArgumentException(__('仅可删除 Toolkit 上传的文件。', 'mao-tk-zibll-toolkit'));
        }
        $response = $this->request('DELETE', $key, array(), '', '');
        if (wp_remote_retrieve_response_code($response) !== 204) {
            throw new \RuntimeException(__('R2 未确认删除，请刷新文件列表核对。', 'mao-tk-zibll-toolkit'));
        }
    }

    public function create(string $key): string
    {
        $response = $this->request('POST', $key, array('uploads' => ''), '', 'application/octet-stream');
        $upload_id = (string) $this->xml($response, 'InitiateMultipartUploadResult')->UploadId;
        if ($upload_id === '' || strlen($upload_id) > 2048) {
            throw new \RuntimeException(__('R2 没有返回有效的上传编号。', 'mao-tk-zibll-toolkit'));
        }
        return $upload_id;
    }

    public function part_url(string $key, string $upload_id, int $part): string
    {
        $path = $this->path($key);
        $date = gmdate('Ymd\THis\Z');
        $scope = substr($date, 0, 8) . '/auto/s3/aws4_request';
        $query = array(
            'partNumber' => (string) $part,
            'uploadId' => $upload_id,
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->access_key . '/' . $scope,
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => '900',
            'X-Amz-SignedHeaders' => 'host'
        );
        $canonical_query = $this->query($query);
        $canonical = "PUT\n$path\n$canonical_query\nhost:{$this->host}\n\nhost\nUNSIGNED-PAYLOAD";
        return "https://{$this->host}$path?$canonical_query&X-Amz-Signature=" .
            $this->signature($canonical, $date, $scope);
    }

    public function parts(string $key, string $upload_id): ?array
    {
        $response = $this->request('GET', $key, array('uploadId' => $upload_id, 'max-parts' => '1000'), '', '', array(404));
        if (wp_remote_retrieve_response_code($response) === 404) {
            return null;
        }
        $xml = $this->xml($response, 'ListPartsResult');
        if ((string) $xml->IsTruncated === 'true') {
            throw new \RuntimeException(__('分片数量超过当前上传上限。', 'mao-tk-zibll-toolkit'));
        }
        $parts = array();
        foreach ($xml->Part as $part) {
            $number = (int) $part->PartNumber;
            $etag = (string) $part->ETag;
            if ($number < 1 || isset($parts[$number]) || !preg_match('/\A"[a-f0-9]{32}"\z/i', $etag)) {
                throw new \RuntimeException(__('R2 返回了无效的分片信息。', 'mao-tk-zibll-toolkit'));
            }
            $parts[$number] = array('size' => (int) $part->Size, 'etag' => $etag);
        }
        return $parts;
    }

    public function complete(string $key, string $upload_id, array $parts): void
    {
        $body = '<CompleteMultipartUpload>';
        foreach ($parts as $number => $part) {
            $body .= '<Part><PartNumber>' . $number . '</PartNumber><ETag>' . $part['etag'] . '</ETag></Part>';
        }
        $body .= '</CompleteMultipartUpload>';
        $response = $this->request('POST', $key, array('uploadId' => $upload_id), $body, 'application/xml');
        $this->xml($response, 'CompleteMultipartUploadResult');
    }

    public function abort(string $key, string $upload_id): void
    {
        $this->request('DELETE', $key, array('uploadId' => $upload_id), '', '', array(404));
    }

    public function size(string $key): ?int
    {
        $response = $this->request('HEAD', $key, array(), '', '', array(404));
        if (wp_remote_retrieve_response_code($response) === 404) {
            return null;
        }
        $size = (string) wp_remote_retrieve_header($response, 'content-length');
        if ($size === '' || !ctype_digit($size)) {
            throw new \RuntimeException(__('R2 没有返回有效的文件大小。', 'mao-tk-zibll-toolkit'));
        }
        return (int) $size;
    }

    public function put_image(string $key, string $body, string $type): void
    {
        $this->request('PUT', $key, array(), $body, $type, array(), 4096, 8);
    }

    public function get_image(string $key, int $limit): ?string
    {
        $response = $this->request('GET', $key, array(), '', '', array(404), $limit + 1, 8);
        if (wp_remote_retrieve_response_code($response) === 404) {
            return null;
        }
        $body = wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > $limit) {
            throw new \RuntimeException(__('R2 图片大小无效。', 'mao-tk-zibll-toolkit'));
        }
        return $body;
    }

    public function has_image(string $key): bool
    {
        $response = $this->request('HEAD', $key, array(), '', '', array(404), 4096, 5);
        return wp_remote_retrieve_response_code($response) !== 404;
    }

    public function delete_image(string $key): void
    {
        $this->request('DELETE', $key, array(), '', '', array(404), 4096, 5);
    }

    private function path(string $key): string
    {
        $bucket_path = '/' . $this->bucket;
        return $key === ''
            ? $bucket_path
            : $bucket_path . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function query(array $parameters): string
    {
        ksort($parameters, SORT_STRING);
        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function signature(string $canonical, string $date, string $scope): string
    {
        $signing_key = 'AWS4' . $this->secret_key;
        foreach (array(substr($date, 0, 8), 'auto', 's3', 'aws4_request') as $segment) {
            $signing_key = hash_hmac('sha256', $segment, $signing_key, true);
        }
        return hash_hmac('sha256', "AWS4-HMAC-SHA256\n$date\n$scope\n" . hash('sha256', $canonical), $signing_key);
    }

    private function request(
        string $method,
        string $key,
        array $parameters,
        string $body,
        string $content_type,
        array $allowed = array(),
        int $response_limit = 524288,
        int $timeout = 25
    ): array {
        $path = $this->path($key);
        $query = $this->query($parameters);
        $date = gmdate('Ymd\THis\Z');
        $scope = substr($date, 0, 8) . '/auto/s3/aws4_request';
        $hash = hash('sha256', $body);
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = "$method\n$path\n$query\nhost:{$this->host}\nx-amz-content-sha256:$hash\nx-amz-date:$date\n\n$signed_headers\n$hash";
        $headers = array(
            'X-Amz-Content-Sha256' => $hash,
            'X-Amz-Date' => $date,
            'Authorization' =>
                "AWS4-HMAC-SHA256 Credential={$this->access_key}/$scope, SignedHeaders=$signed_headers, Signature=" .
                $this->signature($canonical, $date, $scope)
        );
        if ($content_type !== '') {
            $headers['Content-Type'] = $content_type;
        }
        $response = wp_safe_remote_request("https://{$this->host}$path" . ($query === '' ? '' : '?' . $query), array(
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'redirection' => 0,
            'limit_response_size' => $response_limit
        ));
        if (is_wp_error($response)) {
            throw new \RuntimeException(__('WordPress 无法连接 R2。请稍后重试。', 'mao-tk-zibll-toolkit'), 503);
        }
        $status = wp_remote_retrieve_response_code($response);
        if (($status < 200 || $status >= 300) && !in_array($status, $allowed, true)) {
            throw new \RuntimeException(
                __('R2 返回 HTTP ', 'mao-tk-zibll-toolkit') .
                    $status .
                    __('。请核对桶、上传凭据或连接后重试。', 'mao-tk-zibll-toolkit'),
                (int) $status
            );
        }
        return $response;
    }

    private function xml(array $response, string $expected): \SimpleXMLElement
    {
        $body = wp_remote_retrieve_body($response);
        $xml =
            stripos($body, '<!DOCTYPE') === false
                ? @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET)
                : false;
        if ($xml === false || $xml->getName() !== $expected) {
            throw new \RuntimeException(__('R2 返回的响应无法读取。请重试。', 'mao-tk-zibll-toolkit'));
        }
        return $xml;
    }
}
