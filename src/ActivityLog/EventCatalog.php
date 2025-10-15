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
        'auth.login_failed_invalid_password' => [
            'label'    => 'Contraseña incorrecta',
            'category' => 'auth',
            'level'    => 'warning',
            'message'  => 'Contraseña incorrecta para el usuario {{context.username}}.',
        ],
        'auth.login_failed_unknown_user' => [
            'label'    => 'Intento con cuenta inexistente',
            'category' => 'auth',
            'level'    => 'warning',
            'message'  => 'Intento de acceso con una cuenta no registrada ({{context.username}}).',
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
        'user.registration_notified' => [
            'label'    => 'Nuevo registro notificado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => 'Se envió el aviso interno del registro de {{context.user_email}}.',
        ],
        'user.verification_sent' => [
            'label'    => 'Código de verificación enviado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => 'Código de verificación enviado a {{context.user_email}}.',
        ],
        'user.verification_resent' => [
            'label'    => 'Código de verificación reenviado',
            'category' => 'user',
            'level'    => 'warning',
            'message'  => 'Se volvió a enviar el código de verificación a {{context.user_email}}.',
        ],
        'user.verification_verified' => [
            'label'    => 'Correo verificado',
            'category' => 'user',
            'level'    => 'info',
            'message'  => 'El usuario {{context.user_email}} verificó su correo electrónico.',
        ],
        'user.verification_welcome_sent' => [
            'label'    => 'Correo de bienvenida enviado',
            'category' => 'communication',
            'level'    => 'info',
            'message'  => 'Se envió el correo de bienvenida a {{context.user_email}}.',
        ],
        'user.verification_cleanup' => [
            'label'    => 'Limpieza de verificaciones caducadas',
            'category' => 'system',
            'level'    => 'info',
            'message'  => 'Se limpiaron {{context.tokens}} códigos caducados y {{context.windows}} ventanas de reenvío.',
        ],
        'client.offers_updated' => [
            'label'    => 'Ofertas de cliente actualizadas',
            'category' => 'user',
            'level'    => 'info',
            'message'  => '{{actor_name}} actualizó las ofertas de {{context.client_name}}.',
        ],
        'client.commercials_updated' => [
            'label'    => 'Comerciales de cliente actualizados',
            'category' => 'user',
            'level'    => 'info',
            'message'  => '{{actor_name}} actualizó los comerciales asignados de {{context.client_name}}.',
        ],
        'auth.password_recovery.invalid_user' => [
            'label'    => 'Solicitud de restablecimiento sin cuenta',
            'category' => 'auth',
            'level'    => 'warning',
            'message'  => 'Intento de restablecer contraseña para una cuenta no registrada ({{context.username}}).',
        ],
        'guarantee.created' => [
            'label'    => 'Inicio de nueva garantía',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => '{{context.initiator_label}} ha iniciado una nueva garantía.',
        ],
        'guarantee.updated' => [
            'label'    => 'Garantía actualizada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Actualización en la garantía {{context.guarantee_label}}.',
        ],
        'guarantee.status_changed' => [
            'label'    => 'Estado de garantía actualizado',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'La garantía {{context.guarantee_label}} ahora está en estado {{context.new_status_label}}.',
        ],
        'guarantee.contracted' => [
            'label'    => 'Garantía contratada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => 'Garantía {{context.guarantee_label}} contratada. Estado actual: {{context.current_state_label}}.',
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
            'label'    => 'Certificado generado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Certificado generado para {{context.guarantee_label}}.',
        ],
        'document.downloaded' => [
            'label'    => 'Documento descargado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => '{{context.document_label}} descargado de {{context.guarantee_label}}.',
        ],
        'document.generated' => [
            'label'    => 'Documento generado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => '{{context.document_label}} generado para {{context.guarantee_label}}.',
        ],
        'document.signature_added' => [
            'label'    => 'Documento firmado',
            'category' => 'document',
            'level'    => 'info',
            'message'  => 'Se añadió una firma al documento {{context.document_type}}.',
        ],
        'email.sent' => [
            'label'    => 'Correo enviado',
            'category' => 'communication',
            'level'    => 'info',
            'message'  => 'Enviado correo {{context.guarantee_label}} a {{context.email_primary_label}} ({{context.email_to}}).',
        ],
        'email.failed' => [
            'label'    => 'Correo con error',
            'category' => 'communication',
            'level'    => 'error',
            'message'  => 'Error al enviar correo {{context.guarantee_label}} a {{context.email_primary_label}} ({{context.email_to}}).',
        ],
        'email.skipped' => [
            'label'    => 'Correo omitido',
            'category' => 'communication',
            'level'    => 'warning',
            'message'  => 'Correo de {{context.guarantee_label}} omitido ({{context.skip_reason_label}}).',
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
            'label'    => 'Garantía actualizada',
            'category' => 'guarantee',
            'level'    => 'info',
            'message'  => '{{context.payment_method_label}} por {{context.payment_actor_label}}.',
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
