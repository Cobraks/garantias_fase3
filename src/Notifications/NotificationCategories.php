<?php

namespace GarantiasOnline360VO\Notifications;

use function __;
use function current_user_can;
use function sanitize_key;

if (! defined('ABSPATH')) {
    exit;
}

class NotificationCategories
{
    /**
     * Raw category blueprint keyed by slug.
     *
     * @return array<string, array{label:string, icons:array<int, string>, permission:string}>
     */
    private static function definitionMap(): array
    {
        return [
            'login' => [
                'label' => __('Inicio de sesión', 'garantias-online-360vo'),
                'icons' => ['login', 'logout'],
                'permission' => 'login',
            ],
            'payments' => [
                'label' => __('Pagos', 'garantias-online-360vo'),
                'icons' => ['payment', 'sell', 'iban'],
                'permission' => 'management',
            ],
            'guarantees' => [
                'label' => __('Garantías', 'garantias-online-360vo'),
                'icons' => ['new_shield', 'note_event', 'cancel_guarantee'],
                'permission' => 'management',
            ],
            'registrations' => [
                'label' => __('Registros', 'garantias-online-360vo'),
                'icons' => ['check_shield', 'person_add'],
                'permission' => 'management',
            ],
            'other' => [
                'label' => __('Otras notificaciones', 'garantias-online-360vo'),
                'icons' => [],
                'permission' => 'default',
            ],
        ];
    }

    /**
     * Returns the sanitized definition for a key if available.
     *
     * @return array{key:string,label:string,icons:array<int, string>,permission:string}|null
     */
    private static function definition(string $key): ?array
    {
        $map = self::definitionMap();
        if (! isset($map[$key])) {
            return null;
        }

        $entry = $map[$key];
        $icons = array_values(
            array_unique(
                array_map(
                    static fn($icon) => sanitize_key((string) $icon),
                    $entry['icons']
                )
            )
        );

        return [
            'key' => $key,
            'label' => $entry['label'],
            'icons' => $icons,
            'permission' => $entry['permission'],
        ];
    }

    /**
     * Whether the current user can access the permission bucket.
     */
    private static function userCan(string $permission): bool
    {
        switch ($permission) {
            case 'login':
                return current_user_can('administrator');
            case 'management':
                return current_user_can('administrator')
                    || current_user_can('go_director_comercial')
                    || current_user_can('go_garantias');
            default:
                return true;
        }
    }

    /**
     * Returns the category keys the current user can access.
     *
     * @return array<int, string>
     */
    public static function allowedKeys(): array
    {
        $allowed = [];
        foreach (self::definitionMap() as $key => $definition) {
            if (self::userCan($definition['permission'])) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Returns the category definitions the current user may request.
     *
     * @return array<int, array{key:string,label:string,icons:array<int,string>}>
     */
    public static function allowedDefinitions(): array
    {
        $allowed = [];
        foreach (self::definitionMap() as $key => $definition) {
            if (! self::userCan($definition['permission'])) {
                continue;
            }
            $entry = self::definition($key);
            if ($entry === null) {
                continue;
            }
            $allowed[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'icons' => $entry['icons'],
            ];
        }

        return $allowed;
    }

    /**
     * Returns the sanitized icons associated with a category.
     *
     * @return array<int, string>
     */
    public static function iconsFor(string $category): array
    {
        $key = sanitize_key($category);
        $entry = self::definition($key);
        if ($entry === null) {
            return [];
        }

        return $entry['icons'];
    }

    /**
     * Determines if the given category is known.
     */
    public static function exists(string $category): bool
    {
        $key = sanitize_key($category);
        return $key !== '' && self::definition($key) !== null;
    }

    /**
     * Determines if the current user can access a given category.
     */
    public static function userCanAccess(string $category): bool
    {
        $key = sanitize_key($category);
        $entry = self::definition($key);
        if ($entry === null) {
            return false;
        }

        return self::userCan($entry['permission']);
    }

    /**
     * Resolves a category slug for a given icon slug.
     */
    public static function resolveByIcon(?string $iconSlug): string
    {
        $slug = sanitize_key((string) $iconSlug);
        if ($slug === '') {
            return 'other';
        }

        foreach (self::definitionMap() as $key => $definition) {
            foreach ($definition['icons'] as $icon) {
                if (sanitize_key((string) $icon) === $slug) {
                    return $key;
                }
            }
        }

        return 'other';
    }
}
