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
$signature_default_label = esc_html__('+ Subir imagen de firma', 'garantias-online-360vo');
$seal_default_label      = esc_html__('+ Subir imagen de sello', 'garantias-online-360vo');

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_auth_page', 'is_account_page')
);

$profile_image = $user['profile_image']['url'] ?? '';
$profile_alt   = ! empty($user['name']) ? sprintf('Avatar de %s', $user['name']) : 'Avatar de usuario';
$company       = $user['company'] ?? ['name' => '', 'trade_name' => '', 'legal_name' => '', 'tax_id' => '', 'type' => ['label' => '']];
$address = is_array($user['address'] ?? null) ? $user['address'] : [];
$company_address = is_array($company['address'] ?? null) ? $company['address'] : [];

if (! array_filter($company_address)) {
    $company_address = $address;
}

$company_address_parts = array_filter([
    $company_address['street'] ?? '',
    trim(trim((string) ($company_address['zip'] ?? '')) . ' ' . trim((string) ($company_address['city'] ?? ''))),
    trim((string) ($company_address['state'] ?? '')),
]);
$company_address_display = trim(implode(' · ', array_filter($company_address_parts)));

$personal_full_name = trim((string) ($user['full_name'] ?? ''));
if ($personal_full_name === '') {
    $personal_full_name = trim((string) ($user['name'] ?? ''));
}

if ($personal_full_name === '') {
    $personal_full_name = __('No disponible', 'garantias-online-360vo');
}

$registration_email   = sanitize_email((string) ($user['email'] ?? ''));
$notification_email   = sanitize_email((string) ($user['notification_email'] ?? $registration_email));
$custom_notification  = sanitize_email((string) ($user['custom_notification'] ?? ''));
$same_as_registration = $user['same_as_registration'] ?? null;

$show_notification_email = false;
if ($notification_email && $registration_email) {
    $show_notification_email = strcasecmp($notification_email, $registration_email) !== 0;
}

if (! $show_notification_email && $same_as_registration === false && $custom_notification !== '') {
    $show_notification_email = true;
    $notification_email      = $custom_notification;
}

$company_display_name = '';
if (! empty($company['name'])) {
    $company_display_name = $company['name'];
} elseif (! empty($company['trade_name'])) {
    $company_display_name = $company['trade_name'];
} elseif (! empty($company['legal_name'])) {
    $company_display_name = $company['legal_name'];
} else {
    $company_display_name = $user['name'] ?? '';
}

$role_labels = [];
$role_keys = array_map('sanitize_key', (array) ($user['roles'] ?? []));
if ($role_keys && function_exists('wp_roles')) {
    $roles = wp_roles();
    foreach ($role_keys as $role_key) {
        $label = $roles->roles[$role_key]['name'] ?? ucfirst(str_replace('_', ' ', (string) $role_key));
        $role_labels[] = translate_user_role($label);
    }
}

$company_type_label = isset($company['type']['label'])
    ? trim((string) $company['type']['label'])
    : '';
$channel_label = '';

if (in_array('go_profesional', $role_keys, true)) {
    $channel_label = 'Profesional';
    if ($company_type_label !== '') {
        $channel_label .= ' - ' . $company_type_label;
    }
} elseif (in_array('go_gestor', $role_keys, true)) {
    $channel_label = 'Gestoría';
} elseif (in_array('go_comercial', $role_keys, true)) {
    $channel_label = 'Comercial';
} elseif ($company_type_label !== '') {
    $channel_label = $company_type_label;
}

if ($channel_label === '' && ! empty($role_labels)) {
    $channel_label = $role_labels[0];
}

$sections = [
    [
        'id'    => 'account-profile',
        'label' => 'Perfil',
        'icon'  => Svg::icon('person', 'account-nav__icon'),
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
        'label' => 'Certificados',
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
            <div class="account-summary__media">
                <div class="account-summary__avatar" aria-hidden="true">
                    <img
                        src="<?php echo esc_url($profile_image ?: ($user['avatar_url'] ?? '')); ?>"
                        alt="<?php echo esc_attr($profile_alt); ?>"
                        width="72"
                        height="72"
                    >
                </div>
                <input
                    id="account-profile-image"
                    class="account-summary__upload-input"
                    type="file"
                    name="account_profile_image"
                    accept="image/*"
                    hidden
                >
                <button
                    type="button"
                    class="account-summary__upload"
                    data-profile-upload="account-profile-image"
                >
                    <?php echo esc_html__('Cambiar imagen de perfil', 'garantias-online-360vo'); ?>
                </button>
            </div>
            <div class="account-summary__info">
                <h1 class="account-summary__company"><?php echo esc_html($company_display_name); ?></h1>
                <?php if (! empty($user['email'])) : ?>
                    <p class="account-summary__email"><?php echo esc_html($user['email']); ?></p>
                <?php endif; ?>
                <?php if ($channel_label !== '') : ?>
                    <span class="account-summary__badge"><?php echo esc_html($channel_label); ?></span>
                <?php endif; ?>
                <div class="account-summary__actions">
                    <button
                        type="button"
                        class="account-summary__save disabled"
                        data-account-save
                        disabled
                        aria-disabled="true"
                    >
                        <?php echo Svg::icon('save', 'account-summary__save-icon'); ?>
                        <span><?php echo esc_html__('Guardar cambios', 'garantias-online-360vo'); ?></span>
                    </button>
                </div>
            </div>
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
                <?php echo Svg::icon('person', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <div class="account-section__title">
                        <h2>Perfil</h2>
                        <button
                            type="button"
                            class="account-help__trigger"
                            data-account-help-trigger
                            aria-controls="account-profile-help"
                            aria-expanded="false"
                        >
                            <?php echo Svg::icon('help', 'account-help__icon'); ?>
                            <span class="screen-reader-text">Ayuda sobre la gestión de datos del perfil</span>
                        </button>
                    </div>
                    <p>Información general y datos de contacto vinculados a tu cuenta.</p>
                    <div
                        class="account-help account-help--hidden account-section__help"
                        id="account-profile-help"
                        hidden
                        role="region"
                        aria-live="polite"
                    >
                        <div class="account-help__body">
                            <p>Si necesitas modificar alguno de estos datos, ponte en contacto con 360VO.</p>
                        </div>
                        <button
                            type="button"
                            class="account-help__close"
                            aria-label="Cerrar ayuda"
                            data-account-help-dismiss
                        >
                            <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                        </button>
                    </div>
                </div>
            </header>
            <div class="account-card-grid account-card-grid--profile">
                <div class="account-card account-card--details account-card--personal">
                    <h3>Datos personales</h3>
                    <dl class="account-card__list">
                        <div>
                            <dt>Nombre</dt>
                            <dd><?php echo esc_html($personal_full_name); ?></dd>
                        </div>
                        <div>
                            <dt>Teléfono de contacto</dt>
                            <dd>
                                <?php if (! empty($user['phone'])) : ?>
                                    <span><?php echo esc_html($user['phone']); ?></span>
                                <?php else : ?>
                                    <span class="account-card__placeholder">No disponible</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Correo de inicio de sesión</dt>
                            <dd>
                                <?php if ($registration_email !== '') : ?>
                                    <span><?php echo esc_html($registration_email); ?></span>
                                <?php else : ?>
                                    <span class="account-card__placeholder">No disponible</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if ($show_notification_email) : ?>
                            <div>
                                <dt>Correo de notificaciones</dt>
                                <dd>
                                    <a href="mailto:<?php echo esc_attr($notification_email); ?>"><?php echo esc_html($notification_email); ?></a>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="account-card account-card--contacts account-card--commercial">
                    <h3>Comercial asignado</h3>
                    <?php if (! empty($commercials)) : ?>
                        <ul class="account-commercials">
                            <?php foreach ($commercials as $commercial) : ?>
                                <?php
                                $commercial_avatar = $commercial['profile_image']['url'] ?? '';
                                $commercial_name   = $commercial['name'] ?? '';
                                $commercial_phone  = $commercial['phone'] ?? '';
                                $commercial_email  = $commercial['email'] ?? '';
                                ?>
                                <li class="account-commercials__item">
                                    <div class="account-commercials__avatar" aria-hidden="true">
                                        <?php if ($commercial_avatar) : ?>
                                            <img
                                                src="<?php echo esc_url($commercial_avatar); ?>"
                                                alt=""
                                                loading="lazy"
                                                width="56"
                                                height="56"
                                            >
                                        <?php else : ?>
                                            <?php echo Svg::icon('person', 'account-commercials__avatar-icon'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="account-commercials__body">
                                        <span class="account-commercials__name"><?php echo esc_html($commercial_name); ?></span>
                                        <div class="account-commercials__meta">
                                            <?php if ($commercial_phone !== '') : ?>
                                                <a class="account-commercials__meta-item" href="tel:<?php echo esc_attr($formatPhoneHref($commercial_phone)); ?>">
                                                    <?php echo Svg::icon('phone', 'account-commercials__meta-icon'); ?>
                                                    <span><?php echo esc_html($commercial_phone); ?></span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($commercial_email !== '') : ?>
                                                <a class="account-commercials__meta-item" href="mailto:<?php echo esc_attr($commercial_email); ?>">
                                                    <?php echo Svg::icon('email', 'account-commercials__meta-icon'); ?>
                                                    <span><?php echo esc_html($commercial_email); ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="account-card__empty">Pendiente de asignar.</p>
                    <?php endif; ?>
                </div>
                <div class="account-card account-card--details account-card--company">
                    <h3>Datos de la empresa</h3>
                    <dl class="account-card__list">
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
                        <?php if (! empty($company['tax_id'])) : ?>
                            <div>
                                <dt>CIF</dt>
                                <dd class="account-card__code"><?php echo esc_html($company['tax_id']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($company_address_display !== '') : ?>
                            <div>
                                <dt>Dirección</dt>
                                <dd><?php echo esc_html($company_address_display); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </article>

        <article id="account-notifications" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('email', 'account-section__icon'); ?>
                <div class="account-section__content">
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
                            <div
                                class="account-help account-help--hidden"
                                id="account-notification-help"
                                hidden
                                role="region"
                                aria-live="polite"
                            >
                                <div class="account-help__body">
                                    <p>
                                        Escribe la dirección donde quieres recibir avisos y certificados. El correo con el que accedes seguirá siendo el que uses para iniciar sesión.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="account-help__close"
                                    aria-label="Cerrar ayuda"
                                    data-account-help-dismiss
                                >
                                    <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                                </button>
                            </div>
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

            $sepa_full_fields = [];

            $sepa_half_fields = [
                'nombre_deudor',
                'direccion_deudor',
                'numero_cuenta',
                'swift_bic',
            ];

            $sepa_quarter_fields = [
                'codigo_postal',
                'poblacion',
                'provincia',
                'pais_deudor',
            ];

            $has_sepa_values = array_filter(array_map(static function ($field) {
                return trim((string) ($field['value'] ?? ''));
            }, $sepa_field_lookup));

            $sepa_documents = is_array($sepa_info['documents'] ?? null) ? $sepa_info['documents'] : [];
            $pending_document = ! empty($sepa_documents['pending']);
            $signed_document  = ! empty($sepa_documents['signed']);
            $has_generated_mandate = $pending_document || $signed_document || ($sepa_info['status'] !== null);

        ?>
        <article id="account-payments" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('payment', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <div class="account-section__title">
                        <h2>Pagos</h2>
                        <button
                            type="button"
                            class="account-help__trigger"
                            aria-controls="account-payments-help"
                            aria-expanded="false"
                            data-account-help-trigger
                        >
                            <?php echo Svg::icon('help', 'account-help__icon'); ?>
                            <span class="screen-reader-text">Ver información sobre métodos de pago</span>
                        </button>
                    </div>
                    <p>Elige cómo prefieres abonar tus garantías y prepara tu domiciliación bancaria cuando quieras.</p>
                </div>
            </header>
            <div
                class="account-help account-help--hidden"
                id="account-payments-help"
                hidden
                role="region"
                aria-live="polite"
            >
                <div class="account-help__body account-help__body--payments">
                    <div class="account-help__columns">
                        <section class="account-help__column">
                            <h3>Domiciliación bancaria</h3>
                            <ul class="account-help__bullets account-help__bullets--pros">
                                <li>Activa las garantías en el momento, sin esperas ni comprobaciones manuales.</li>
                                <li>Automatiza los cobros y evita olvidos o errores al generar transferencias.</li>
                                <li>Recibirás un cargo por cada garantía contratada, con justificante automático.</li>
                            </ul>
                        </section>
                        <section class="account-help__column">
                            <h3>Transferencia bancaria</h3>
                            <ul class="account-help__bullets account-help__bullets--cons">
                                <li>Recuerda realizar la transferencia antes de 48&nbsp;horas desde la contratación.</li>
                                <li>La garantía queda pendiente hasta que validamos el ingreso.</li>
                                <li>Debes enviar el justificante y coordinarte con tu contacto comercial.</li>
                            </ul>
                        </section>
                    </div>
                </div>
                <button
                    type="button"
                    class="account-help__close"
                    aria-label="Cerrar ayuda"
                    data-account-help-dismiss
                >
                    <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                </button>
            </div>
            <div class="account-card-grid account-card-grid--payments">
                <div
                    class="account-card account-card--payments-summary"
                    data-payment-activation
                    data-state="<?php echo esc_attr($activation_state); ?>"
                >
                    <h3>Configura tu método de pago</h3>
                    <p class="account-card__status">
                        Método de pago actual: <strong><?php echo esc_html($current_method_label); ?></strong>
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
                </div>
                <div
                    class="account-card account-card--payments-detail"
                    data-payment-detail
                    data-state="<?php echo esc_attr($activation_state); ?>"
                    data-generated="<?php echo $has_generated_mandate ? 'true' : 'false'; ?>"
                >
                    <div class="account-card__header">
                        <h3>Domiciliación bancaria</h3>
                        <button
                            type="button"
                            class="account-help__trigger"
                            aria-controls="account-payments-sepa-help"
                            aria-expanded="false"
                            data-account-help-trigger
                        >
                            <?php echo Svg::icon('help', 'account-help__icon'); ?>
                            <span class="screen-reader-text">Cómo completar la domiciliación bancaria</span>
                        </button>
                    </div>
                    <div
                        class="account-help account-help--hidden"
                        id="account-payments-sepa-help"
                        hidden
                        role="region"
                        aria-live="polite"
                    >
                        <div class="account-help__body">
                            <p>Completa los campos del mandato y selecciona «Generar SEPA». Te enviaremos el documento listo para firmar y devolverlo a 360VO.</p>
                        </div>
                        <button
                            type="button"
                            class="account-help__close"
                            aria-label="Cerrar ayuda"
                            data-account-help-dismiss
                        >
                            <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                        </button>
                    </div>
                    <p
                        class="account-card__intro"
                        data-payment-state="disabled"
                        <?php echo $activation_state === 'disabled' ? '' : 'hidden'; ?>
                    >
                        Activa la domiciliación para generar el mandato SEPA y olvidarte de gestionar transferencias manuales.
                    </p>
                    <p
                        class="account-card__intro"
                        data-payment-state="enabled"
                        data-payment-awaiting
                        <?php echo $activation_state === 'enabled' ? '' : 'hidden'; ?>
                    >
                        Completa los datos del titular y genera tu mandato SEPA. Después podrás firmarlo y subirlo desde aquí.
                    </p>
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
                                        if (in_array($field_key, $sepa_half_fields, true)) {
                                            $field_classes[] = 'account-form__field--half';
                                        }
                                        if (in_array($field_key, $sepa_quarter_fields, true)) {
                                            $field_classes[] = 'account-form__field--quarter';
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
                                    if (in_array($field_key, $sepa_half_fields, true)) {
                                        $field_classes[] = 'account-form__field--half';
                                    }
                                    if (in_array($field_key, $sepa_quarter_fields, true)) {
                                        $field_classes[] = 'account-form__field--quarter';
                                    }
                                ?>
                                <div class="<?php echo esc_attr(implode(' ', $field_classes)); ?>">
                                    <div class="account-input-container">
                                        <input
                                            type="text"
                                            id="<?php echo esc_attr($field_id); ?>"
                                            name="account-sepa[<?php echo esc_attr($field_key); ?>]"
                                            class="account-input"
                                            value="<?php echo esc_attr($field_value); ?>"
                                            placeholder=" "
                                            autocomplete="off"
                                        >
                                        <label class="account-input__label" for="<?php echo esc_attr($field_id); ?>">
                                            <?php echo esc_html($field_label); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div
                            class="account-payments__actions"
                            data-payment-state="enabled"
                            data-payment-awaiting
                            <?php echo $activation_state === 'enabled' && ! $has_generated_mandate ? '' : 'hidden'; ?>
                        >
                            <button
                                type="button"
                                class="account-button"
                                data-payment-generate
                                <?php disabled($has_generated_mandate); ?>
                            >
                                Generar SEPA
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <article id="account-documents" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('check_shield', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <div class="account-section__title">
                        <h2>Certificados</h2>
                        <button
                            type="button"
                            class="account-help__trigger"
                            aria-controls="account-certificates-help"
                            aria-expanded="false"
                            data-account-help-trigger
                        >
                            <?php echo Svg::icon('help', 'account-help__icon'); ?>
                            <span class="screen-reader-text">Ver información sobre firma y sello</span>
                        </button>
                    </div>
                    <p>Prepara la firma y el sello que incluiremos en tus certificados.</p>
                </div>
            </header>
            <div
                class="account-help account-help--hidden"
                id="account-certificates-help"
                hidden
                role="region"
                aria-live="polite"
            >
                <div class="account-help__body">
                    <p>Puedes subir tu firma y sello para que aparezcan en los certificados que emitimos. Mantén los archivos actualizados para evitar rechazos.</p>
                </div>
                <button
                    type="button"
                    class="account-help__close"
                    aria-label="Cerrar ayuda"
                    data-account-help-dismiss
                >
                    <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                </button>
            </div>
            <div class="account-card-grid">
                <div class="account-card account-card--certificates" data-certificates-card>
                    <div class="account-card__header">
                        <h3>Firma y sello para certificados</h3>
                        <button
                            type="button"
                            class="account-help__trigger"
                            aria-controls="account-certificates-usage"
                            aria-expanded="false"
                            data-account-help-trigger
                        >
                            <?php echo Svg::icon('help', 'account-help__icon'); ?>
                            <span class="screen-reader-text">Ver consejos para subir firma y sello</span>
                        </button>
                    </div>
                    <div
                        class="account-help account-help--hidden"
                        id="account-certificates-usage"
                        hidden
                        role="region"
                        aria-live="polite"
                    >
                        <div class="account-help__body">
                            <p>Utiliza imágenes legibles, sin fondos y con buena resolución. Podrás revisar la previsualización antes de guardar los cambios.</p>
                        </div>
                        <button
                            type="button"
                            class="account-help__close"
                            aria-label="Cerrar ayuda"
                            data-account-help-dismiss
                        >
                            <?php echo Svg::icon('close', 'account-help__close-icon'); ?>
                        </button>
                    </div>
                    <div class="account-certificates__uploads">
                        <div class="form-row">
                            <div class="input-container">
                                <div
                                    class="file-upload"
                                    id="signature-upload"
                                    data-default-label="<?php echo esc_attr($signature_default_label); ?>"
                                >
                                    <div class="file-label">
                                        <?php
                                        $signature_label = $signature_default_label;
                                        if (! empty($document_signature['name']) && is_string($document_signature['name'])) {
                                            $signature_label = esc_html($document_signature['name']);
                                        }
                                        echo $signature_label;
                                        ?>
                                    </div>
                                    <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                    <input type="file" id="signature" class="file-input" accept="image/*">
                                    <div
                                        class="file-preview"
                                        id="signature-preview"
                                        <?php echo empty($document_signature['url']) ? 'hidden aria-hidden="true"' : ''; ?>
                                    >
                                        <?php if (! empty($document_signature['url'])) : ?>
                                            <img src="<?php echo esc_url($document_signature['url']); ?>" alt="Previsualización de la firma" loading="lazy">
                                        <?php else : ?>
                                            <img src="" alt="Previsualización de la firma" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                    <button
                                        type="button"
                                        class="file-remove"
                                        data-file-remove="signature"
                                        <?php echo empty($document_signature['url']) ? 'hidden' : ''; ?>
                                    >
                                        <?php echo esc_html__('Eliminar imagen', 'garantias-online-360vo'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="input-container">
                                <div
                                    class="file-upload"
                                    id="stamp-upload"
                                    data-default-label="<?php echo esc_attr($seal_default_label); ?>"
                                >
                                    <div class="file-label">
                                        <?php
                                        $seal_label = $seal_default_label;
                                        if (! empty($document_seal['name']) && is_string($document_seal['name'])) {
                                            $seal_label = esc_html($document_seal['name']);
                                        }
                                        echo $seal_label;
                                        ?>
                                    </div>
                                    <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                    <input type="file" id="stamp" class="file-input" accept="image/*">
                                    <div
                                        class="file-preview"
                                        id="stamp-preview"
                                        <?php echo empty($document_seal['url']) ? 'hidden aria-hidden="true"' : ''; ?>
                                    >
                                        <?php if (! empty($document_seal['url'])) : ?>
                                            <img src="<?php echo esc_url($document_seal['url']); ?>" alt="Previsualización del sello" loading="lazy">
                                        <?php else : ?>
                                            <img src="" alt="Previsualización del sello" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                    <button
                                        type="button"
                                        class="file-remove"
                                        data-file-remove="stamp"
                                        <?php echo empty($document_seal['url']) ? 'hidden' : ''; ?>
                                    >
                                        <?php echo esc_html__('Eliminar imagen', 'garantias-online-360vo'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
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
