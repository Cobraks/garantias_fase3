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

        return $this->generate_keys();
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

        $resource = openssl_pkey_new($config);
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
}
