<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class Base64KeyNormalizer
{
    public static function normalize($value): string
    {
        if (! is_string($value)) {
            if (is_scalar($value)) {
                $value = (string) $value;
            } else {
                return '';
            }
        }

        $filtered = preg_replace('~[^A-Za-z0-9\-_=+\/]+~', '', $value);
        if (! is_string($filtered)) {
            return '';
        }

        $filtered = trim($filtered);
        if ($filtered === '') {
            return '';
        }

        $converted = strtr($filtered, '-_', '+/');
        $padding = strlen($converted) % 4;
        if ($padding) {
            $converted .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($converted, true);
        if ($decoded === false) {
            // If decoding fails, fall back to the cleaned original string so we don't
            // discard keys that might already be URL-safe encoded.
            return $filtered;
        }

        $reencoded = base64_encode($decoded);

        return rtrim(strtr($reencoded, '+/', '-_'), '=');
    }
}
