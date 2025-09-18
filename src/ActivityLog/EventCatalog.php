<?php

namespace GarantiasOnline360VO\ActivityLog;

if (! defined('ABSPATH')) {
    exit;
}

class EventCatalog
{
    /**
     * Catálogo base de eventos conocidos.
     * @var array<string, array<string, mixed>>
     */
    private const EVENTS = [
        'auth.login_success' => [
            'label'    => 'Inicio de sesión correcto',
            'category' => 'auth',
            'level'    => 'info',
            'message'  => 'Inicio de sesión satisfactorio de {{actor_name}}.',
        ],
        'auth.login_failed' => [
            'label'    => 'Intento de acceso fallido',
            'category' => 'auth',
            'level'    => 'warning',
            'message'  => 'Intento fallido de inicio de sesión con el usuario {{context.username}}.',
        ],
        'auth.logout' => [
            'label'    => 'Cierre de sesión',
            'category' => 'auth',
            'level'    => 'info',
            'message'  => '{{actor_name}} ha cerrado la sesión.',
        ],
        'user.registered' => [
            'label'    => 'Nuevo usuario registrado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => 'Alta del usuario {{context.user_email}} ({{context.user_role}}).',
        ],
        'user.role_changed' => [
            'label'    => 'Rol de usuario actualizado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => 'Se asignó el rol {{context.new_role}} a {{context.user_email}}.',
        ],
        'user.profile_updated' => [
            'label'    => 'Perfil actualizado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => '{{actor_name}} actualizó su perfil.',
        ],
        'user.password_reset' => [
            'label'    => 'Contraseña restablecida',
            'category' => 'user',
            'level'    => 'warning',
            'message'  => 'Se restableció la contraseña del usuario {{context.user_email}}.',
        ],
        'user.password_requested' => [
            'label'    => 'Solicitud de restablecimiento',
            'category' => 'user',
            'level'    => 'warning',
            'message'  => 'Se solicitó el restablecimiento de contraseña para {{context.user_email}}.',
        ],
        'guarantee.created' => [
            'label'    => 'Garantía creada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Alta de la garantía {{context.guarantee_label}}.',
        ],
        'guarantee.updated' => [
            'label'    => 'Garantía actualizada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Actualización en la garantía {{context.guarantee_label}}.',
        ],
        'guarantee.status_changed' => [
            'label'    => 'Estado de garantía cambiado',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Estado cambiado a {{context.new_status}} en la garantía {{context.guarantee_label}}.',
        ],
        'guarantee.assigned' => [
            'label'    => 'Garantía asignada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Garantía {{context.guarantee_label}} asignada a {{context.assignee}}.',
        ],
        'guarantee.reassigned' => [
            'label'    => 'Garantía reasignada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Garantía {{context.guarantee_label}} reasignada de {{context.old_assignee}} a {{context.new_assignee}}.',
        ],
        'guarantee.expired' => [
            'label'    => 'Garantía caducada',
            'category' => 'guarantee',
            'level'    => 'warning',
            'message'  => 'Garantía {{context.guarantee_label}} marcada como caducada.',
        ],
        'guarantee.deleted' => [
            'label'    => 'Garantía eliminada',
            'category' => 'guarantee',
            'level'    => 'warning',
            'message'  => 'Garantía {{context.guarantee_label}} eliminada.',
        ],
        'document.uploaded' => [
            'label'    => 'Documento subido',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Se subió el documento {{context.document_type}} para {{context.guarantee_label}}.',
        ],
        'document.downloaded' => [
            'label'    => 'Documento descargado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Descarga del documento {{context.document_type}} de {{context.guarantee_label}}.',
        ],
        'document.generated' => [
            'label'    => 'Documento generado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Se generó el documento {{context.document_type}}.',
        ],
        'document.signature_added' => [
            'label'    => 'Documento firmado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Se añadió una firma al documento {{context.document_type}}.',
        ],
        'email.sent' => [
            'label'    => 'Email enviado',
            'category' => 'communication',
            'level'    => 'info',
            'message'  => 'Correo {{context.template}} enviado a {{context.recipients}}.',
        ],
        'email.failed' => [
            'label'    => 'Email fallido',
            'category' => 'communication',
            'level'    => 'error',
            'message'  => 'Fallo al enviar correo {{context.template}} a {{context.recipients}}.',
        ],
        'email.skipped' => [
            'label'    => 'Email omitido',
            'category' => 'communication',
            'level'    => 'warning',
            'message'  => 'Correo {{context.template}} omitido ({{context.reason}}).',
        ],
        'notification.scheduled' => [
            'label'    => 'Aviso programado',
            'category' => 'communication',
            'level'    => 'info',
            'message'  => 'Se programó la notificación {{context.notification}}.',
        ],
        'notification.cancelled' => [
            'label'    => 'Aviso cancelado',
            'category' => 'communication',
            'level'    => 'warning',
            'message'  => 'Notificación {{context.notification}} cancelada.',
        ],
        'payment.recorded' => [
            'label'    => 'Pago registrado',
            'category' => 'finance',
            'level'    => 'info',
            'message'  => 'Pago registrado por {{context.amount}} en la garantía {{context.guarantee_label}}.',
        ],
        'payment.failed' => [
            'label'    => 'Pago fallido',
            'category' => 'finance',
            'level'    => 'error',
            'message'  => 'Error en el cobro de {{context.amount}} en la garantía {{context.guarantee_label}}.',
        ],
        'settings.updated' => [
            'label'    => 'Ajustes modificados',
            'category' => 'system',
            'level'    => 'info',
            'message'  => 'Se actualizaron los ajustes {{context.setting_key}}.',
        ],
        'integrations.synced' => [
            'label'    => 'Integración sincronizada',
            'category' => 'system',
            'level'    => 'info',
            'message'  => 'Sincronización completada con {{context.integration}}.',
        ],
        'cron.executed' => [
            'label'    => 'Tarea programada',
            'category' => 'system',
            'level'    => 'info',
            'message'  => 'Se ejecutó la tarea cron {{context.hook}}.',
        ],
        'error.critical' => [
            'label'    => 'Error crítico',
            'category' => 'system',
            'level'    => 'error',
            'message'  => 'Error crítico: {{context.message}}.',
        ],
        'custom.note' => [
            'label'    => 'Nota manual',
            'category' => 'custom',
            'level'    => 'info',
            'message'  => '{{message}}',
        ],
    ];

    private const CATEGORY_LABELS = [
        'auth'        => 'Autenticación y accesos',
        'user'        => 'Usuarios',
        'guarantee'   => 'Garantías',
        'document'    => 'Documentos',
        'communication'=> 'Comunicaciones',
        'finance'     => 'Finanzas y contratos',
        'system'      => 'Sistema',
        'custom'      => 'Personalizado',
    ];

    public static function get(string $event_type): array
    {
        if (! isset(self::EVENTS[$event_type])) {
            return [];
        }

        return self::translate_event(self::EVENTS[$event_type]);
    }

    public static function has(string $event_type): bool
    {
        return isset(self::EVENTS[$event_type]);
    }

    public static function categories(): array
    {
        $translated = [];
        foreach (self::CATEGORY_LABELS as $key => $label) {
            $translated[$key] = __($label, 'garantias-online-360vo');
        }

        return $translated;
    }

    public static function category_options(): array
    {
        $options = [];
        foreach (self::categories() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }
        return $options;
    }

    public static function event_options(): array
    {
        $options = [];
        foreach (self::EVENTS as $value => $event) {
            $translated = self::translate_event($event);
            $options[] = [
                'value'    => $value,
                'label'    => $translated['label'] ?? $value,
                'category' => $translated['category'] ?? 'system',
                'level'    => $translated['level'] ?? 'info',
            ];
        }
        return $options;
    }

    public static function to_public_catalog(): array
    {
        return [
            'categories'  => self::category_options(),
            'event_types' => self::event_options(),
        ];
    }

    private static function translate_event(array $event): array
    {
        if (isset($event['label'])) {
            $event['label'] = __($event['label'], 'garantias-online-360vo');
        }

        if (isset($event['message'])) {
            $event['message'] = __($event['message'], 'garantias-online-360vo');
        }

        if (isset($event['category']) && isset(self::CATEGORY_LABELS[$event['category']])) {
            $event['category_label'] = __(
                self::CATEGORY_LABELS[$event['category']],
                'garantias-online-360vo'
            );
        }

        return $event;
    }
}
