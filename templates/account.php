<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;

$is_auth_page     = false;
$is_account_page  = true;
$account          = $account ?? [];
$user             = $account['user'] ?? [];
$commercials      = $account['commercials'] ?? [];
$documents        = $account['documents'] ?? [];
$payments         = $account['payments'] ?? [];
$document_signature = is_array($documents['signature'] ?? null) ? $documents['signature'] : [];
$document_seal       = is_array($documents['seal'] ?? null) ? $documents['seal'] : [];
$documents_flag      = $documents['add_to_certificates'] ?? null;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_auth_page', 'is_account_page')
);

$address = $user['address'] ?? [];
$country      = isset($address['country']) ? (string) $address['country'] : '';
$address_parts = array_filter([
    $address['street'] ?? '',
    trim(trim((string) ($address['zip'] ?? '')) . ' ' . trim((string) ($address['city'] ?? ''))),
    trim((string) ($address['state'] ?? '') . ($country !== '' ? ' · ' . $country : '')),
]);

$profile_image = $user['profile_image']['url'] ?? '';
$profile_alt   = ! empty($user['name']) ? sprintf('Avatar de %s', $user['name']) : 'Avatar de usuario';
$company       = $user['company'] ?? ['trade_name' => '', 'legal_name' => ''];

$role_labels = [];
if (! empty($user['roles']) && function_exists('wp_roles')) {
    $roles = wp_roles();
    foreach ((array) $user['roles'] as $role_key) {
        $label = $roles->roles[$role_key]['name'] ?? ucfirst(str_replace('_', ' ', (string) $role_key));
        $role_labels[] = translate_user_role($label);
    }
}

$sections = [
    [
        'id'    => 'account-profile',
        'label' => 'Perfil profesional',
        'icon'  => Svg::icon('user', 'account-nav__icon'),
    ],
    [
        'id'    => 'account-notifications',
        'label' => 'Notificaciones',
        'icon'  => Svg::icon('email', 'account-nav__icon'),
    ],
    [
        'id'    => 'account-payments',
        'label' => 'Pagos',
        'icon'  => Svg::icon('payment', 'account-nav__icon'),
    ],
    [
        'id'    => 'account-documents',
        'label' => 'Documentos para certificados',
        'icon'  => Svg::icon('check_shield', 'account-nav__icon'),
    ],
];

$formatPhoneHref = static function ($phone) {
    if (! is_string($phone)) {
        return '';
    }

    $digits = preg_replace('/[^0-9+]/', '', $phone);
    return $digits ?: '';
};

?>

<div class="account-page" data-view="account">
    <aside class="account-page__sidebar">
        <section class="account-summary">
            <div class="account-summary__avatar" aria-hidden="true">
                <img
                    src="<?php echo esc_url($profile_image ?: ($user['avatar_url'] ?? '')); ?>"
                    alt="<?php echo esc_attr($profile_alt); ?>"
                    width="96"
                    height="96"
                >
            </div>
            <div class="account-summary__info">
                <h1 class="account-summary__name"><?php echo esc_html($user['name'] ?? ''); ?></h1>
                <?php if (! empty($role_labels)) : ?>
                    <p class="account-summary__role"><?php echo esc_html(implode(' · ', $role_labels)); ?></p>
                <?php endif; ?>
                <?php if (! empty($user['email'])) : ?>
                    <p class="account-summary__contact">
                        <?php echo Svg::icon('email', 'account-summary__icon'); ?>
                        <a href="mailto:<?php echo esc_attr($user['email']); ?>"><?php echo esc_html($user['email']); ?></a>
                    </p>
                <?php endif; ?>
                <?php if (! empty($user['phone'])) : ?>
                    <p class="account-summary__contact">
                        <?php echo Svg::icon('phone', 'account-summary__icon'); ?>
                        <a href="tel:<?php echo esc_attr($formatPhoneHref($user['phone'])); ?>"><?php echo esc_html($user['phone']); ?></a>
                    </p>
                <?php endif; ?>
            </div>
            <?php if (! empty($address_parts)) : ?>
                <ul class="account-summary__address">
                    <?php foreach ($address_parts as $line) : ?>
                        <li><?php echo esc_html($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <nav class="account-page__nav" aria-label="Secciones de la cuenta">
            <ul>
                <?php foreach ($sections as $index => $section) : ?>
                    <li>
                        <a
                            href="#<?php echo esc_attr($section['id']); ?>"
                            data-target="<?php echo esc_attr($section['id']); ?>"
                            <?php echo $index === 0 ? 'aria-current="page"' : ''; ?>
                        >
                            <?php echo $section['icon']; ?>
                            <span><?php echo esc_html($section['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <section class="account-page__content">
        <article id="account-profile" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('user', 'account-section__icon'); ?>
                <div>
                    <h2>Perfil profesional</h2>
                    <p>Información general y datos de contacto vinculados a tu cuenta.</p>
                </div>
            </header>
            <div class="account-card-grid">
                <div class="account-card">
                    <h3>Datos básicos</h3>
                    <dl class="account-card__list">
                        <div>
                            <dt>Usuario</dt>
                            <dd><?php echo esc_html($user['username'] ?? ''); ?></dd>
                        </div>
                        <?php if (! empty($company['trade_name'])) : ?>
                            <div>
                                <dt>Nombre comercial</dt>
                                <dd><?php echo esc_html($company['trade_name']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($company['legal_name'])) : ?>
                            <div>
                                <dt>Razón social</dt>
                                <dd><?php echo esc_html($company['legal_name']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>Correo de acceso</dt>
                            <dd><?php echo esc_html($user['email'] ?? ''); ?></dd>
                        </div>
                        <?php if (! empty($user['phone'])) : ?>
                            <div>
                                <dt>Teléfono</dt>
                                <dd>
                                    <a href="tel:<?php echo esc_attr($formatPhoneHref($user['phone'])); ?>">
                                        <?php echo esc_html($user['phone']); ?>
                                    </a>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($address_parts)) : ?>
                            <div>
                                <dt>Dirección</dt>
                                <dd>
                                    <?php echo esc_html(implode(' · ', $address_parts)); ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($role_labels)) : ?>
                            <div>
                                <dt>Roles en la plataforma</dt>
                                <dd><?php echo esc_html(implode(' · ', $role_labels)); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="account-card">
                    <h3>Comerciales asignados</h3>
                    <?php if (! empty($commercials)) : ?>
                        <ul class="account-contacts">
                            <?php foreach ($commercials as $commercial) : ?>
                                <?php
                                $commercial_avatar = $commercial['profile_image']['url'] ?? '';
                                $commercial_name   = $commercial['name'] ?? '';
                                ?>
                                <li class="account-contacts__item">
                                    <div class="account-contacts__avatar" aria-hidden="true">
                                        <?php if ($commercial_avatar) : ?>
                                            <img
                                                src="<?php echo esc_url($commercial_avatar); ?>"
                                                alt=""
                                                loading="lazy"
                                                width="56"
                                                height="56"
                                            >
                                        <?php else : ?>
                                            <?php echo Svg::icon('user', 'account-contacts__avatar-icon'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="account-contacts__body">
                                        <span class="account-contacts__name"><?php echo esc_html($commercial_name); ?></span>
                                        <div class="account-contacts__actions">
                                            <?php if (! empty($commercial['phone'])) : ?>
                                                <a
                                                    class="account-contacts__action account-contacts__action--phone"
                                                    href="tel:<?php echo esc_attr($formatPhoneHref($commercial['phone'])); ?>"
                                                >
                                                    <?php echo Svg::icon('phone', 'account-contacts__action-icon'); ?>
                                                    <span><?php echo esc_html($commercial['phone']); ?></span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (! empty($commercial['email'])) : ?>
                                                <a
                                                    class="account-contacts__action account-contacts__action--email"
                                                    href="mailto:<?php echo esc_attr($commercial['email']); ?>"
                                                >
                                                    <?php echo Svg::icon('email', 'account-contacts__action-icon'); ?>
                                                    <span><?php echo esc_html($commercial['email']); ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="account-card__empty">No tienes comerciales asociados en este momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <article id="account-notifications" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('email', 'account-section__icon'); ?>
                <div>
                    <h2>Notificaciones</h2>
                    <p>Configura cómo te avisamos por correo y desde tu dispositivo.</p>
                </div>
            </header>
            <div class="account-card-grid">
                <?php
                $registration_email   = $user['email'] ?? '';
                $notification_email   = $user['notification_email'] ?? $registration_email;
                $custom_notification  = $user['custom_notification'] ?? '';
                $same_as_registration = $user['same_as_registration'] ?? null;

                if ($notification_email === '') {
                    $notification_email = $registration_email;
                }

                $email_status = 'Actualmente enviamos avisos y certificados a <strong>'
                    . esc_html($notification_email ?: $registration_email)
                    . '</strong>.';

                if ($same_as_registration === true || $notification_email === $registration_email) {
                    $email_status = 'Los avisos y certificados se están enviando al correo con el que accedes: <strong>'
                        . esc_html($registration_email)
                        . '</strong>.';
                } elseif ($same_as_registration === false && $custom_notification !== '') {
                    $email_status = 'Tienes una dirección personalizada para avisos y certificados: <strong>'
                        . esc_html($notification_email)
                        . '</strong>.';
                }
                ?>
                <?php
                $custom_input_value = $same_as_registration === false ? $custom_notification : '';
                ?>
                <div class="account-card account-card--form">
                    <h3>Avisos por correo electrónico</h3>
                    <p class="account-card__status">
                        <?php echo wp_kses($email_status, ['strong' => []]); ?>
                    </p>
                    <form class="account-form" action="#" method="post" novalidate>
                        <div class="account-field">
                            <div class="account-field__label-wrapper">
                                <label class="account-field__label" for="account-notification-email">
                                    Dirección alternativa para notificaciones
                                </label>
                                <button
                                    type="button"
                                    class="account-help__trigger"
                                    data-account-help-trigger
                                    aria-controls="account-notification-help"
                                    aria-expanded="false"
                                >
                                    <?php echo Svg::icon('help', 'account-help__icon'); ?>
                                    <span class="screen-reader-text">Más información sobre la dirección alternativa</span>
                                </button>
                            </div>
                            <input
                                type="email"
                                id="account-notification-email"
                                name="account-notification-email"
                                class="account-input"
                                value="<?php echo esc_attr($custom_input_value); ?>"
                                placeholder="nombre@empresa.com"
                                autocomplete="off"
                            >
                            <p class="account-help account-help--hidden" id="account-notification-help" hidden>
                                Escribe la dirección donde quieres recibir avisos y certificados. El correo con el que accedes seguirá siendo el que uses para iniciar sesión.
                            </p>
                        </div>
                    </form>
                </div>

                <div class="account-card account-card--notifications" data-notifications-card>
                    <h3>Notificaciones del navegador</h3>
                    <p class="account-card__intro">
                        Activa las alertas del sistema para enterarte al instante de las novedades de tus garantías.
                    </p>
                    <div class="account-card__actions">
                        <button type="button" class="account-button" data-notifications-request>
                            <?php echo Svg::icon('notifications', 'account-button__icon'); ?>
                            Activar notificaciones
                        </button>
                        <p class="account-status account-status--info" data-notifications-status>
                            Revisa los permisos disponibles en tu navegador.
                        </p>
                    </div>
                </div>
            </div>
        </article>

        <?php
            $selected_payment_method = $payments['selected_method'] ?? 'transferencia';
            $sepa_info               = is_array($payments['sepa'] ?? null) ? $payments['sepa'] : [];
            $sepa_status_label       = $sepa_info['status_label'] ?? 'Sin información del mandato';
            $sepa_status_variant     = $sepa_info['status_variant'] ?? 'info';
            $sepa_locked             = (bool) ($sepa_info['locked'] ?? false);
            $sepa_fields             = is_array($sepa_info['fields'] ?? null) ? $sepa_info['fields'] : [];
            $method_labels           = [
                'domiciliacion' => 'Domiciliación bancaria',
                'transferencia' => 'Transferencia bancaria',
            ];
            $current_method_label    = $method_labels[$selected_payment_method] ?? $method_labels['transferencia'];
            $activation_state        = $sepa_locked
                ? 'locked'
                : ($selected_payment_method === 'domiciliacion' ? 'enabled' : 'disabled');
            $sepa_field_lookup       = [];

            foreach ($sepa_fields as $field) {
                $field_name = (string) ($field['name'] ?? '');
                if ($field_name === '' && isset($field['label'])) {
                    $field_name = sanitize_title((string) $field['label']);
                }

                if ($field_name !== '') {
                    $sepa_field_lookup[$field_name] = $field;
                }
            }

            $sepa_field_order = [
                'nombre_deudor',
                'direccion_deudor',
                'codigo_postal',
                'poblacion',
                'provincia',
                'pais_deudor',
                'numero_cuenta',
                'swift_bic',
            ];

            $sepa_full_fields = [
                'nombre_deudor',
                'direccion_deudor',
                'numero_cuenta',
                'swift_bic',
            ];

            $has_sepa_values = array_filter(array_map(static function ($field) {
                return trim((string) ($field['value'] ?? ''));
            }, $sepa_field_lookup));
        ?>
        <article id="account-payments" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('payment', 'account-section__icon'); ?>
                <div>
                    <h2>Pagos</h2>
                    <p>Consulta el método activo y prepara tu domiciliación bancaria cuando estés listo.</p>
                </div>
            </header>
            <div class="account-card-grid account-card-grid--payments">
                <div
                    class="account-card account-card--payments-summary"
                    data-payment-activation
                    data-state="<?php echo esc_attr($activation_state); ?>"
                >
                    <h3>Configura tu método de pago</h3>
                    <p class="account-payments__current">
                        <strong>Método de pago actual:</strong> <?php echo esc_html($current_method_label); ?>
                    </p>
                    <?php if ($activation_state === 'disabled') : ?>
                        <p class="account-status account-status--warning" data-payment-state="disabled">
                            Cuando contrates una garantía, tendrás 48&nbsp;horas para realizar la transferencia y avisar a tu equipo comercial.
                        </p>
                    <?php else : ?>
                        <p class="account-status account-status--success" data-payment-state="enabled locked">
                            Las garantías se activan automáticamente gracias a tu domiciliación bancaria.
                        </p>
                    <?php endif; ?>
                    <p class="account-status account-status--info" data-payment-state="disabled" <?php echo $activation_state === 'disabled' ? '' : 'hidden'; ?>>
                        Activa la domiciliación bancaria para automatizar el cobro y evitar gestiones manuales.
                    </p>
                    <p class="account-status account-status--info" data-payment-state="enabled" <?php echo $activation_state === 'enabled' ? '' : 'hidden'; ?>>
                        Completa tus datos bancarios para generar el mandato SEPA.
                    </p>
                    <label class="account-toggle">
                        <input
                            type="checkbox"
                            class="account-toggle__input"
                            data-payment-toggle
                            value="1"
                            <?php checked($activation_state !== 'disabled'); ?>
                            <?php disabled($sepa_locked); ?>
                        >
                        <span class="account-toggle__label">Activar domiciliación bancaria</span>
                    </label>
                    <?php if ($sepa_locked) : ?>
                        <p class="account-card__note">Tu mandato SEPA está validado. Si necesitas hacer cambios, contacta con tu equipo de 360VO.</p>
                    <?php endif; ?>
                </div>
                <div
                    class="account-card account-card--payments-detail"
                    data-payment-detail
                    data-state="<?php echo esc_attr($activation_state); ?>"
                >
                    <h3>Domiciliación bancaria</h3>
                    <div class="account-card__status account-card__status--<?php echo esc_attr($sepa_status_variant); ?>">
                        <strong>Estado del mandato:</strong> <?php echo esc_html($sepa_status_label); ?>
                    </div>
                    <div
                        class="account-payments__message"
                        data-payment-state="disabled"
                        <?php echo $activation_state === 'disabled' ? '' : 'hidden'; ?>
                    >
                        <p class="account-card__intro">Activa la domiciliación para generar el mandato SEPA y olvidarte de las transferencias.</p>
                    </div>
                    <?php if ($sepa_locked) : ?>
                        <?php if ($has_sepa_values) : ?>
                            <div
                                class="account-form account-form--sepa account-form--sepa-readonly"
                                data-payment-state="locked"
                                <?php echo $activation_state === 'locked' ? '' : 'hidden'; ?>
                            >
                                <?php foreach ($sepa_field_order as $field_key) : ?>
                                    <?php
                                        $field = $sepa_field_lookup[$field_key] ?? null;
                                        $value = trim((string) ($field['value'] ?? ''));
                                        if ($value === '') {
                                            continue;
                                        }
                                        $field_label = (string) ($field['label'] ?? $field_key);
                                        $field_id    = 'account-sepa-' . sanitize_title($field_key);
                                        $field_classes = ['account-form__field', 'account-form__field--readonly'];
                                        if (in_array($field_key, $sepa_full_fields, true)) {
                                            $field_classes[] = 'account-form__field--full';
                                        }
                                    ?>
                                    <div class="<?php echo esc_attr(implode(' ', $field_classes)); ?>">
                                        <span class="account-form__label"><?php echo esc_html($field_label); ?></span>
                                        <span class="account-form__value" id="<?php echo esc_attr($field_id); ?>-value"><?php echo esc_html($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="account-card__note" data-payment-state="locked" <?php echo $activation_state === 'locked' ? '' : 'hidden'; ?>>Si necesitas actualizar los datos del mandato, contacta con tu equipo de 360VO.</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <div
                            class="account-form account-form--sepa"
                            data-payment-state="enabled"
                            <?php echo $activation_state === 'enabled' ? '' : 'hidden'; ?>
                        >
                            <?php foreach ($sepa_field_order as $field_key) : ?>
                                <?php
                                    $field = $sepa_field_lookup[$field_key] ?? null;
                                    $field_label = (string) ($field['label'] ?? ucfirst(str_replace('_', ' ', $field_key)));
                                    $field_value = (string) ($field['value'] ?? '');
                                    $field_id    = 'account-sepa-' . sanitize_title($field_key);
                                    $field_classes = ['account-field', 'account-form__field'];
                                    if (in_array($field_key, $sepa_full_fields, true)) {
                                        $field_classes[] = 'account-form__field--full';
                                    }
                                ?>
                                <div class="<?php echo esc_attr(implode(' ', $field_classes)); ?>">
                                    <div class="account-field__label-wrapper">
                                        <label class="account-field__label" for="<?php echo esc_attr($field_id); ?>">
                                            <?php echo esc_html($field_label); ?>
                                        </label>
                                    </div>
                                    <input
                                        type="text"
                                        id="<?php echo esc_attr($field_id); ?>"
                                        name="account-sepa[<?php echo esc_attr($field_key); ?>]"
                                        class="account-input"
                                        value="<?php echo esc_attr($field_value); ?>"
                                        placeholder="<?php echo esc_attr($field_label); ?>"
                                        autocomplete="off"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div
                            class="account-payments__actions"
                            data-payment-state="enabled"
                            <?php echo $activation_state === 'enabled' ? '' : 'hidden'; ?>
                        >
                            <button type="button" class="account-button" disabled>Generar SEPA</button>
                            <p class="account-card__note">Te hemos enviado un correo con el mandato SEPA y todas las instrucciones.</p>
                            <div class="account-upload" aria-live="polite">
                                <p>Fírmalo y súbelo aquí:</p>
                                <button type="button" class="account-button account-button--ghost" disabled>Subir mandato firmado</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <article id="account-documents" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('check_shield', 'account-section__icon'); ?>
                <div>
                    <h2>Documentos para certificados</h2>
                    <p>Gestiona la firma y el sello que aparecen en tus certificados.</p>
                </div>
            </header>
            <div class="account-card-grid">
                <div class="account-card">
                    <h3>Firma y sello</h3>
                    <dl class="account-card__list">
                        <div>
                            <dt>Añadir automáticamente</dt>
                            <dd>
                                <?php if ($documents_flag === true) : ?>
                                    Sí, se añaden a cada certificado emitido.
                                <?php elseif ($documents_flag === false) : ?>
                                    No, los certificados se generan sin firma ni sello.
                                <?php else : ?>
                                    Sin configurar.
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if (! empty($document_signature['url'])) : ?>
                            <div>
                                <dt>Firma</dt>
                                <dd>
                                    <a href="<?php echo esc_url($document_signature['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        Ver archivo
                                    </a>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($document_seal['url'])) : ?>
                            <div>
                                <dt>Sello</dt>
                                <dd>
                                    <a href="<?php echo esc_url($document_seal['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        Ver archivo
                                    </a>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <p class="account-card__note">
                        Para actualizar estos documentos envíanos la nueva firma o sello desde tu canal habitual de soporte.
                    </p>
                </div>
            </div>
        </article>
    </section>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_account_page')
);
