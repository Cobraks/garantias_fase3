<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class VapidKeyManager
{
    private const OPTION_PUBLIC_KEY = 'go360_vapid_public_key';
    private const OPTION_PRIVATE_KEY = 'go360_vapid_private_key';

    /**
     * @return array{public: string, private: string}
     */
    public function get_keys(): array
    {
        $public = (string) get_option(self::OPTION_PUBLIC_KEY, '');
        $private = (string) get_option(self::OPTION_PRIVATE_KEY, '');

        if ($public !== '' && $private !== '') {
            return [
                'public'  => $public,
                'private' => $private,
            ];
        }

        $generated = $this->safely_generate_keys();
        if ($generated !== null) {
            return $generated;
        }

        return [
            'public'  => '',
            'private' => '',
        ];
    }

    /**
     * @return array{public: string, private: string}
     */
    private function generate_keys(): array
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];

        $resource = $this->create_key_resource($config);
        if (! $resource) {
            throw new \RuntimeException('Unable to generate VAPID keys');
        }

        $details = openssl_pkey_get_details($resource);
        $private_pem = '';
        openssl_pkey_export($resource, $private_pem);

        if (! isset($details['key']) || $private_pem === '') {
            throw new \RuntimeException('Invalid VAPID key generation');
        }

        $public_pem = (string) $details['key'];
        $public = $this->convert_public_to_uncompressed($public_pem);
        $private_compact = $this->extract_private_key($details, $private_pem);

        update_option(self::OPTION_PUBLIC_KEY, $public);
        update_option(self::OPTION_PRIVATE_KEY, $private_compact);

        return [
            'public'  => $public,
            'private' => $private_compact,
        ];
    }

    /**
     * Attempt to generate keys and log any errors without bubbling exceptions up the stack.
     *
     * @return array{public: string, private: string}|null
     */
    private function safely_generate_keys(): ?array
    {
        try {
            return $this->generate_keys();
        } catch (\Throwable $exception) {
            error_log('[go360] VAPID generation failed: ' . $exception->getMessage());
        }

        return null;
    }

    private function convert_public_to_uncompressed(string $pem): string
    {
        $pem = trim($pem);
        $pem = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', $pem);
        $binary = base64_decode($pem, true);
        if ($binary === false) {
            throw new \RuntimeException('Invalid VAPID public key');
        }

        $offset = strpos($binary, "\x04");
        if ($offset === false) {
            throw new \RuntimeException('Invalid EC public key format');
        }

        $key = substr($binary, $offset, 65);
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $details
     */
    private function extract_private_key(array $details, string $pem): string
    {
        if (isset($details['ec']['d']) && is_string($details['ec']['d'])) {
            return $this->base64url_encode($details['ec']['d']);
        }

        return $this->convert_private_to_compact($pem);
    }

    private function convert_private_to_compact(string $pem): string
    {
        $pem = $this->strip_pem_headers($pem);
        $binary = base64_decode($pem, true);
        if ($binary === false) {
            throw new \RuntimeException('Invalid VAPID private key');
        }

        $offset = strpos($binary, "\x04\x20");
        if ($offset === false) {
            throw new \RuntimeException('Invalid EC private key format');
        }
        $key = substr($binary, $offset + 2, 32);

        return $this->base64url_encode($key);
    }

    private function strip_pem_headers(string $pem): string
    {
        $pem = trim($pem);
        $pem = preg_replace('/-----BEGIN [^-]+-----/', '', $pem) ?? $pem;
        $pem = preg_replace('/-----END [^-]+-----/', '', $pem) ?? $pem;
        return str_replace(["\n", "\r"], '', $pem);
    }

    private function base64url_encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $config
     * @return resource|false
     */
    private function create_key_resource(array $config)
    {
        $resource = openssl_pkey_new($config);
        if ($resource !== false) {
            return $resource;
        }

        $config_path = $this->locate_openssl_config();
        if ($config_path !== null) {
            $config['config'] = $config_path;
            $resource = openssl_pkey_new($config);
            if ($resource !== false) {
                return $resource;
            }
        }

        return false;
    }

    private function locate_openssl_config(): ?string
    {
        $env = getenv('OPENSSL_CONF');
        if (is_string($env) && $env !== '') {
            $path = $this->normalise_config_path($env);
            if ($path !== null) {
                return $path;
            }
        }

        if (function_exists('openssl_get_cert_locations')) {
            $locations = openssl_get_cert_locations();
            foreach (['default_conf_filename', 'default_cert_file', 'ini_cafile', 'ini_capath'] as $key) {
                if (! isset($locations[$key])) {
                    continue;
                }

                $candidate = $this->normalise_config_path($locations[$key]);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        $candidates = [
            ini_get('openssl.cnf'),
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
            dirname((string) PHP_BINARY) . '/openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            $path = $this->normalise_config_path($candidate);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    private function normalise_config_path($path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (is_file($path) && is_readable($path)) {
            return $path;
        }

        if (is_dir($path)) {
            $candidate = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'openssl.cnf';
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
