<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class WebPushClient
{
    /** @var VapidKeyManager */
    private $vapid;

    /** @var int */
    private $ttl;

    public function __construct(?VapidKeyManager $vapid = null, int $ttl = 2419200)
    {
        $this->vapid = $vapid ?: new VapidKeyManager();
        $this->ttl = $ttl;
    }

    /**
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $payload
     */
    public function send(array $subscription, array $payload): bool
    {
        $endpoint = isset($subscription['endpoint']) ? esc_url_raw((string) $subscription['endpoint']) : '';
        if ($endpoint === '') {
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('DEBUG - Sending to endpoint: ' . $endpoint);
            error_log('DEBUG - Subscription data: ' . print_r([
                'has_public_key' => ! empty($subscription['public_key'] ?? $subscription['publicKey'] ?? $subscription['p256dh']),
                'has_auth_token' => ! empty($subscription['auth_token'] ?? $subscription['authToken'] ?? $subscription['auth']),
                'encoding'       => $subscription['content_encoding'] ?? $subscription['contentEncoding'] ?? 'none',
            ], true));
        }

        $encoding = $this->normalise_encoding($subscription['content_encoding'] ?? $subscription['contentEncoding'] ?? '');
        if ($encoding === '') {
            $encoding = 'aes128gcm';
        }

        if ($encoding !== 'aes128gcm') {
            do_action('go360/push/log', 'unsupported_encoding', [
                'endpoint' => $endpoint,
                'encoding' => $encoding,
            ]);

            return false;
        }

        $public_key = (string) ($subscription['public_key'] ?? $subscription['publicKey'] ?? $subscription['p256dh'] ?? '');
        $auth_token = (string) ($subscription['auth_token'] ?? $subscription['authToken'] ?? $subscription['auth'] ?? '');
        if ($public_key === '' || $auth_token === '') {
            return false;
        }

        $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($body)) {
            return false;
        }

        $keys = $this->vapid->get_keys();
        if (empty($keys['public']) || empty($keys['private'])) {
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] Dispatch attempt to ' . substr($endpoint, 0, 120));
            error_log('[GO360 Push DEBUG] Subscriber key length: ' . strlen($public_key));
            error_log('[GO360 Push DEBUG] Auth token length: ' . strlen($auth_token));
            error_log('[GO360 Push DEBUG] VAPID public key available: ' . (! empty($keys['public']) ? 'yes' : 'no'));
        }

        do_action('go360/push/log', 'dispatch_attempt', [
            'endpoint' => $endpoint,
        ]);

        try {
            $encrypted = $this->encrypt_payload($body, $public_key, $auth_token);
        } catch (\Throwable $exception) {
            do_action('go360/push/log', 'encryption_failure', [
                'endpoint' => $endpoint,
                'error'    => $exception->getMessage(),
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GO360 Push DEBUG] Encryption failure: ' . $exception->getMessage());
            }

            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] Payload encrypted. Body length: ' . strlen($encrypted['body']));
        }

        $audience = $this->determine_audience($endpoint);
        if ($audience === null) {
            return false;
        }

        $authorization = $this->build_vapid_authorization($audience, $keys);
        if ($authorization === null) {
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] VAPID authorization generated. Audience: ' . $audience);
        }

        $headers = [
            'TTL'              => (string) $this->ttl,
            'Content-Encoding' => 'aes128gcm',
            'Content-Type'     => 'application/octet-stream',
            'Content-Length'   => (string) strlen($encrypted['body']),
            'Authorization'    => $authorization,
            'Encryption'       => sprintf(
                'salt=%s;rs=4096',
                $this->base64url_encode($encrypted['salt'])
            ),
            'Crypto-Key'       => sprintf(
                'dh=%s;p256ecdsa=%s',
                $this->base64url_encode($encrypted['public_key']),
                $keys['public']
            ),
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] Request headers: ' . wp_json_encode([
                'TTL'              => $headers['TTL'],
                'Content-Encoding' => $headers['Content-Encoding'],
                'Content-Length'   => $headers['Content-Length'],
            ]));
        }

        $response = wp_remote_request($endpoint, [
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => $encrypted['body'],
            'timeout' => 10,
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] HTTP request finished. WP_Error: ' . (is_wp_error($response) ? 'yes' : 'no'));
        }

        if (is_wp_error($response)) {
            do_action('go360/push/log', 'dispatch_failed', [
                'endpoint' => $endpoint,
                'error'    => $response->get_error_message(),
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GO360 Push DEBUG] Request error message: ' . $response->get_error_message());
            }

            return false;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);
        $body_snippet = substr(wp_remote_retrieve_body($response), 0, 500);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push DEBUG] Response status: ' . $status . ' message: ' . $message);
            if ($body_snippet !== '') {
                error_log('[GO360 Push DEBUG] Response body snippet: ' . $body_snippet);
            }
        }

        if ($status >= 200 && $status < 300) {
            do_action('go360/push/log', 'dispatch_success', [
                'endpoint' => $endpoint,
                'status'   => $status,
            ]);

            return true;
        }

        do_action('go360/push/log', 'dispatch_failed', [
            'endpoint' => $endpoint,
            'status'   => $status,
            'body'     => $body_snippet,
            'message'  => $message,
        ]);

        return false;
    }

    /**
     * @return array{body: string, salt: string, public_key: string}
     */
    private function encrypt_payload(string $payload, string $user_public_key, string $user_auth_token): array
    {
        $subscriber_key = $this->base64url_decode($user_public_key);
        $auth_secret = $this->base64url_decode($user_auth_token);

        if ($subscriber_key === '' || strlen($subscriber_key) !== 65) {
            throw new \RuntimeException('Invalid subscriber public key.');
        }

        $salt = random_bytes(16);

        $local_key = $this->create_ephemeral_key();

        $local_details = openssl_pkey_get_details($local_key);
        if (! $local_details || ! isset($local_details['key'])) {
            throw new \RuntimeException('Unable to inspect local key.');
        }

        $local_public_binary = $this->extract_uncompressed_public_key($local_details['key']);

        $subscriber_pem = $this->convert_uncompressed_key_to_pem($subscriber_key);
        $subscriber_resource = openssl_pkey_get_public($subscriber_pem);
        if (! $subscriber_resource) {
            throw new \RuntimeException('Unable to load subscriber key.');
        }

        $shared_secret = openssl_pkey_derive($subscriber_resource, $local_key, 32);
        if (! is_string($shared_secret) || $shared_secret === '') {
            throw new \RuntimeException('Unable to derive shared secret.');
        }

        $ikm = $this->derive_ikm($auth_secret, $shared_secret, $subscriber_key, $local_public_binary);
        $cek = $this->hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
        $nonce = $this->hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);

        $padding_length = apply_filters('go360/push/payload_padding', 0, $payload);
        $padding_length = is_int($padding_length) && $padding_length > 0 ? $padding_length : 0;

        $padding = $padding_length > 0 ? str_repeat("\0", $padding_length) : '';
        $plain_text = pack('n', $padding_length) . $padding . $payload;
        $tag = '';
        $ciphertext = openssl_encrypt($plain_text, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt payload.');
        }

        $body = $salt
            . pack('N', 4096)
            . pack('C', strlen($local_public_binary))
            . $local_public_binary
            . $ciphertext
            . $tag;

        if (is_resource($subscriber_resource)) {
            openssl_free_key($subscriber_resource);
        }
        if (is_resource($local_key)) {
            openssl_free_key($local_key);
        }

        return [
            'body'        => $body,
            'salt'        => $salt,
            'public_key'  => $local_public_binary,
        ];
    }

    private function derive_ikm(string $auth_secret, string $shared_secret, string $subscriber_key, string $local_key): string
    {
        if ($auth_secret === '') {
            return $shared_secret;
        }

        $info = "WebPush: info\0" . $subscriber_key . $local_key;

        return $this->hkdf($auth_secret, $shared_secret, $info, 32);
    }

    /**
     * @param array{public: string, private: string} $keys
     */
    private function build_vapid_authorization(string $audience, array $keys): ?string
    {
        $public_raw = $this->base64url_decode($keys['public']);
        $private_raw = $this->base64url_decode($keys['private']);

        if (strlen($public_raw) !== 65 || strlen($private_raw) !== 32) {
            return null;
        }

        $subject = apply_filters('go360/push/vapid_subject', $this->default_subject(), $audience);
        if (! is_string($subject) || $subject === '') {
            $subject = $this->default_subject();
        }

        $jwt = $this->create_vapid_jwt($audience, $subject, $public_raw, $private_raw);
        if ($jwt === null) {
            return null;
        }

        return 'vapid t=' . $jwt . ', k=' . $keys['public'];
    }

    private function create_vapid_jwt(string $audience, string $subject, string $public_raw, string $private_raw): ?string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES);
        $payload = json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $subject,
        ], JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

        if ($header === false || $payload === false) {
            return null;
        }

        $encoded_header = $this->base64url_encode($header);
        $encoded_payload = $this->base64url_encode($payload);
        $signing_input = $encoded_header . '.' . $encoded_payload;

        $signature = $this->sign_es256($signing_input, $public_raw, $private_raw);
        if ($signature === null) {
            return null;
        }

        return $encoded_header . '.' . $encoded_payload . '.' . $this->base64url_encode($signature);
    }

    private function sign_es256(string $data, string $public_raw, string $private_raw): ?string
    {
        $pem = $this->create_private_pem($private_raw, $public_raw);
        $key = openssl_pkey_get_private($pem);
        if (! $key) {
            return null;
        }

        $signature = '';
        $result = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if (! $result) {
            return null;
        }

        return $this->der_to_jose($signature, 32);
    }

    private function determine_audience(string $endpoint): ?string
    {
        $parts = wp_parse_url($endpoint);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $audience = strtolower($parts['scheme']) . '://' . $parts['host'];

        if (isset($parts['port']) && is_numeric($parts['port'])) {
            $port = (int) $parts['port'];
            if (! $this->is_default_port($parts['scheme'], $port)) {
                $audience .= ':' . $port;
            }
        }

        return $audience;
    }

    private function default_subject(): string
    {
        $email = sanitize_email((string) get_option('admin_email'));
        if ($email !== '') {
            return 'mailto:' . $email;
        }

        return home_url('/');
    }

    private function normalise_encoding($encoding): string
    {
        if (! is_string($encoding)) {
            return '';
        }

        return strtolower(trim($encoding));
    }

    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode(string $data): string
    {
        $data = trim($data);
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($data, true);

        return $decoded === false ? '' : $decoded;
    }

    private function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $result = '';
        $previous = '';
        $block = 0;

        while (strlen($result) < $length) {
            $block++;
            $previous = hash_hmac('sha256', $previous . $info . chr($block), $prk, true);
            $result .= $previous;
        }

        return substr($result, 0, $length);
    }

    private function convert_uncompressed_key_to_pem(string $uncompressed): string
    {
        $der = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200');
        if ($der === false) {
            throw new \RuntimeException('Unable to prepare public key DER prefix.');
        }

        $der .= $uncompressed;

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private function extract_uncompressed_public_key(string $pem): string
    {
        $clean = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', trim($pem));
        $binary = base64_decode($clean, true);
        if ($binary === false) {
            throw new \RuntimeException('Invalid public key PEM.');
        }

        $position = strpos($binary, "\x04");
        if ($position === false) {
            throw new \RuntimeException('Unexpected public key format.');
        }

        $key = substr($binary, $position, 65);
        if ($key === false || strlen($key) !== 65) {
            throw new \RuntimeException('Invalid uncompressed public key.');
        }

        return $key;
    }

    private function create_private_pem(string $private_raw, string $public_raw): string
    {
        $der_prefix = hex2bin('30770201010420');
        $der_suffix = hex2bin('A00706052B8104000AA14403420004');
        if ($der_prefix === false || $der_suffix === false) {
            throw new \RuntimeException('Unable to prepare private key DER components.');
        }

        $der = $der_prefix . $private_raw . $der_suffix . $public_raw;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        $pem .= "-----END EC PRIVATE KEY-----\n";

        return $pem;
    }

    private function der_to_jose(string $der, int $part_length): string
    {
        if ($der === '') {
            throw new \RuntimeException('Invalid DER signature.');
        }

        $bytes = unpack('C*', $der);
        if (! is_array($bytes) || empty($bytes)) {
            throw new \RuntimeException('Invalid DER signature.');
        }

        $position = 1;

        if (($bytes[$position++] ?? null) !== 0x30) {
            throw new \RuntimeException('Invalid DER signature.');
        }

        $this->read_der_length($bytes, $position);

        if (($bytes[$position++] ?? null) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature format (R).');
        }

        $r_length = $this->read_der_length($bytes, $position);
        $r = $this->collect_der_bytes($bytes, $position, $r_length);
        $r = ltrim($r, "\0");
        $r = str_pad($r, $part_length, "\0", STR_PAD_LEFT);

        if (($bytes[$position++] ?? null) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature format (S).');
        }

        $s_length = $this->read_der_length($bytes, $position);
        $s = $this->collect_der_bytes($bytes, $position, $s_length);
        $s = ltrim($s, "\0");
        $s = str_pad($s, $part_length, "\0", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * @param array<int, int> $bytes
     */
    private function read_der_length(array $bytes, int &$position): int
    {
        $length = $bytes[$position++] ?? null;
        if ($length === null) {
            throw new \RuntimeException('Invalid DER length.');
        }

        if ($length & 0x80) {
            $num_bytes = $length & 0x7f;
            $length = 0;
            for ($i = 0; $i < $num_bytes; $i++) {
                $next = $bytes[$position++] ?? null;
                if ($next === null) {
                    throw new \RuntimeException('Invalid DER length.');
                }
                $length = ($length << 8) | $next;
            }
        }

        return $length;
    }

    /**
     * @param array<int, int> $bytes
     */
    private function collect_der_bytes(array $bytes, int &$position, int $length): string
    {
        $chunk = '';
        for ($i = 0; $i < $length; $i++) {
            $value = $bytes[$position++] ?? null;
            if ($value === null) {
                throw new \RuntimeException('Invalid DER structure.');
            }
            $chunk .= chr($value);
        }

        return $chunk;
    }

    private function is_default_port(string $scheme, int $port): bool
    {
        $scheme = strtolower($scheme);
        if ($scheme === 'http' && $port === 80) {
            return true;
        }

        if ($scheme === 'https' && $port === 443) {
            return true;
        }

        return false;
    }

    /**
     * @return resource
     */
    private function create_ephemeral_key()
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];

        $resource = openssl_pkey_new($config);
        if ($resource !== false) {
            return $resource;
        }

        $config_path = VapidKeyManager::discover_openssl_config_path();
        if ($config_path !== null) {
            $config['config'] = $config_path;
            $resource = openssl_pkey_new($config);
            if ($resource !== false) {
                return $resource;
            }
        }

        throw new \RuntimeException('Unable to generate local key pair.');
    }
}
