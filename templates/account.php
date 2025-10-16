<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Svg;

$is_auth_page     = false;
$is_account_page  = true;
$account          = $account ?? [];
$user             = $account['user'] ?? [];
$commercials      = $account['commercials'] ?? [];
$documents        = $account['documents'] ?? [];
$payments         = $account['payments'] ?? [];
$clients          = $account['clients'] ?? [];
$workshop        = is_array($account['workshop'] ?? null) ? $account['workshop'] : [];
$workshop_defaults = [
    'has_workshop'   => false,
    'name'           => '',
    'fiscal_name'    => '',
    'tax_id'         => '',
    'contact_person' => '',
    'phone'          => '',
    'email'          => '',
    'address'        => '',
];
$workshop = array_merge($workshop_defaults, $workshop);
$workshop_has = ! empty($workshop['has_workshop']);
$workshop_fields = [
    [
        'key'          => 'name',
        'label'        => 'Nombre del taller',
        'id'           => 'account-workshop-name',
        'type'         => 'text',
        'autocomplete' => 'organization',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'fiscal_name',
        'label'        => 'Denominación fiscal',
        'id'           => 'account-workshop-fiscal-name',
        'type'         => 'text',
        'autocomplete' => 'organization',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'tax_id',
        'label'        => 'CIF',
        'id'           => 'account-workshop-tax-id',
        'type'         => 'text',
        'autocomplete' => '',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'contact_person',
        'label'        => 'Persona de contacto del taller',
        'id'           => 'account-workshop-contact',
        'type'         => 'text',
        'autocomplete' => 'name',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'phone',
        'label'        => 'Teléfono del taller',
        'id'           => 'account-workshop-phone',
        'type'         => 'tel',
        'autocomplete' => 'tel',
        'inputmode'    => 'tel',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'email',
        'label'        => 'Correo del taller',
        'id'           => 'account-workshop-email',
        'type'         => 'email',
        'autocomplete' => 'email',
        'class'        => 'account-form__field--half',
    ],
    [
        'key'          => 'address',
        'label'        => 'Dirección del taller',
        'id'           => 'account-workshop-address',
        'type'         => 'text',
        'autocomplete' => 'street-address',
        'class'        => 'account-form__field--full',
    ],
];
$document_signature = is_array($documents['signature'] ?? null) ? $documents['signature'] : [];
$document_seal       = is_array($documents['seal'] ?? null) ? $documents['seal'] : [];
$signature_default_label   = esc_html__('+ Subir imagen de firma', 'garantias-online-360vo');
$seal_default_label        = esc_html__('+ Subir imagen de sello', 'garantias-online-360vo');
$procedure_default_label   = esc_html__('+ Subir procedimiento de reclamación', 'garantias-online-360vo');

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
$is_admin_account = in_array('administrator', $role_keys, true);
$is_professional_account = in_array('go_profesional', $role_keys, true);
$is_commercial_account = in_array('go_comercial', $role_keys, true);
$is_individual_account = in_array('go_particular', $role_keys, true);
$is_director_account = in_array('go_director_comercial', $role_keys, true);
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

if ($is_director_account) {
    $channel_label = 'Director Comercial';
} elseif ($is_professional_account) {
    $channel_label = 'Profesional';
    if ($company_type_label !== '') {
        $channel_label .= ' - ' . $company_type_label;
    }
} elseif (in_array('go_gestor', $role_keys, true)) {
    $channel_label = 'Gestoría';
} elseif ($is_commercial_account) {
    $channel_label = 'Comercial';
} elseif ($company_type_label !== '') {
    $channel_label = $company_type_label;
}

if ($channel_label === '' && ! empty($role_labels)) {
    $channel_label = $role_labels[0];
}

$admin_notification_rows       = [];
$admin_notification_next_index = 0;
$admin_reply_to_email          = '';
$admin_claim_document          = [];
$admin_claim_document_size     = '';
$admin_transfer_iban           = '';

if ($is_admin_account && function_exists('get_field')) {
    $notifications_options = get_field('notificaciones', SettingsPage::SUBMENU_SLUG);

    if (is_array($notifications_options)) {
        $notifications_group = $notifications_options['notificaciones_email'] ?? [];

        if (is_array($notifications_group)) {
            $rows = $notifications_group['direcciones_correo'] ?? [];

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $email = sanitize_email((string) ($row['admin_recipients'] ?? ''));
                    $admin_notification_rows[] = [
                        'email' => $email,
                        'bcc'   => ! empty($row['copia_oculta']),
                        'index' => $admin_notification_next_index,
                    ];
                    $admin_notification_next_index++;
                }
            }

            $admin_reply_to_email = sanitize_email((string) ($notifications_group['direccion_respuesta'] ?? ''));
        }
    }

    $documents_options = get_field('documentacion', SettingsPage::SUBMENU_SLUG);

    if (is_array($documents_options) && ! empty($documents_options['procedimiento_de_reclamacion'])) {
        $document_candidate = $documents_options['procedimiento_de_reclamacion'];

        if (is_array($document_candidate)) {
            $admin_claim_document = $document_candidate;
            $filesize_candidate   = $admin_claim_document['filesize'] ?? '';
            if ($filesize_candidate !== '') {
                if (is_numeric($filesize_candidate)) {
                    $admin_claim_document_size = size_format((float) $filesize_candidate);
                } elseif (is_string($filesize_candidate)) {
                    $admin_claim_document_size = trim($filesize_candidate);
                }
            }
        }
    }

    $bank_options = get_field('datos_bancarios', SettingsPage::SUBMENU_SLUG);

    if (is_array($bank_options)) {
        $admin_transfer_iban = trim((string) ($bank_options['iban_360vo'] ?? ''));
    }
}

if (! $admin_notification_rows) {
    $admin_notification_rows[] = [
        'email' => '',
        'bcc'   => false,
        'index' => $admin_notification_next_index,
    ];
    $admin_notification_next_index++;
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
];

if ((! $is_commercial_account || $is_admin_account) && ! $is_individual_account && ! $is_director_account) {
    $sections[] = [
        'id'    => 'account-payments',
        'label' => 'Pagos',
        'icon'  => Svg::icon('payment', 'account-nav__icon'),
    ];

    $sections[] = [
        'id'    => 'account-documents',
        'label' => 'Certificados',
        'icon'  => Svg::icon('check_shield', 'account-nav__icon'),
    ];
}

if ($is_professional_account) {
    $sections[] = [
        'id'    => 'account-workshop',
        'label' => 'Taller',
        'icon'  => Svg::icon('taller', 'account-nav__icon'),
    ];
}

if ($is_commercial_account && ! $is_admin_account) {
    $sections[] = [
        'id'    => 'account-clients',
        'label' => 'Clientes asignados',
        'icon'  => Svg::icon('clients', 'account-nav__icon'),
    ];
}

$formatPhoneHref = static function ($phone) {
    if (! is_string($phone)) {
        return '';
    }

    $digits = preg_replace('/[^0-9+]/', '', $phone);
    return $digits ?: '';
};

?>

<div
    class="account-page"
    data-view="account"
    <?php echo $is_admin_account ? 'data-account-admin="true"' : ''; ?>
>
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
                    <p
                        class="account-status account-status--info account-summary__status"
                        data-account-status
                        hidden
                        aria-hidden="true"
                    ></p>
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
                            <?php if ($is_admin_account) : ?>
                                <p>Gestiona y actualiza esta información directamente desde tu panel. Recuerda guardar los cambios para aplicarlos.</p>
                            <?php else : ?>
                                <p>Si necesitas modificar alguno de estos datos, ponte en contacto con 360VO.</p>
                            <?php endif; ?>
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
                <?php
                $has_assigned_commercials = ! empty($commercials);
                $show_commercial_card = ! $is_admin_account
                    && ! $is_commercial_account
                    && ! $is_director_account
                    && ($has_assigned_commercials || ! $is_individual_account);
                $show_company_card = ! $is_admin_account
                    && ! $is_commercial_account
                    && ! $is_director_account
                    && ! $is_individual_account;
                ?>
                <?php if ($show_commercial_card) : ?>
                    <div class="account-card account-card--contacts account-card--commercial">
                        <h3>Comercial asignado</h3>
                        <?php if ($has_assigned_commercials) : ?>
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
                        <?php elseif (! $is_individual_account) : ?>
                            <p class="account-card__empty">Pendiente de asignar.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($show_company_card) : ?>
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
                <?php endif; ?>
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
                    <?php if ($is_admin_account) : ?>
                        <form class="account-form account-form--notifications account-form--floating" action="#" method="post" novalidate>
                            <div
                                class="account-repeater"
                                data-notification-repeater
                                data-next-index="<?php echo esc_attr($admin_notification_next_index); ?>"
                            >
                                <div class="account-repeater__header">
                                    <span>Correo electrónico</span>
                                    <span>Copia oculta</span>
                                    <span class="screen-reader-text">Acciones</span>
                                </div>
                                <div class="account-repeater__rows" data-repeater-rows>
                                    <?php foreach ($admin_notification_rows as $row) : ?>
                                        <?php
                                        $row_index = (int) ($row['index'] ?? 0);
                                        $email_id  = 'admin-notification-' . $row_index;
                                        $bcc_id    = 'admin-notification-bcc-' . $row_index;
                                        $email_value = (string) ($row['email'] ?? '');
                                        $bcc_enabled = ! empty($row['bcc']);
                                        ?>
                                        <div class="account-repeater__row" data-repeater-row data-repeater-index="<?php echo esc_attr($row_index); ?>">
                                            <div class="account-field account-field--email">
                                                <div class="account-input-container">
                                                    <input
                                                        type="email"
                                                        id="<?php echo esc_attr($email_id); ?>"
                                                        class="account-input"
                                                        name="admin_notifications[recipients][<?php echo esc_attr($row_index); ?>][email]"
                                                        value="<?php echo esc_attr($email_value); ?>"
                                                        placeholder=" "
                                                        autocomplete="off"
                                                        data-repeater-email
                                                    >
                                                    <label class="account-input__label" for="<?php echo esc_attr($email_id); ?>">Correo electrónico</label>
                                                </div>
                                            </div>
                                            <div class="account-field account-field--checkbox">
                                                <label class="account-checkbox account-checkbox--center" for="<?php echo esc_attr($bcc_id); ?>">
                                                    <input
                                                        type="checkbox"
                                                        id="<?php echo esc_attr($bcc_id); ?>"
                                                        name="admin_notifications[recipients][<?php echo esc_attr($row_index); ?>][bcc]"
                                                        value="1"
                                                        <?php checked($bcc_enabled); ?>
                                                        data-repeater-bcc
                                                    >
                                                    <span>Enviar en copia oculta</span>
                                                </label>
                                            </div>
                                            <div class="account-repeater__actions">
                                                <button
                                                    type="button"
                                                    class="account-button account-button--ghost account-repeater__remove"
                                                    data-repeater-remove
                                                >
                                                    <?php echo Svg::icon('close', 'account-button__icon'); ?>
                                                    <span>Eliminar</span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <template data-repeater-template>
                                        <div class="account-repeater__row" data-repeater-row data-repeater-index="__index__">
                                        <div class="account-field account-field--email">
                                            <div class="account-input-container">
                                                <input
                                                    type="email"
                                                    id="admin-notification-__index__"
                                                    class="account-input"
                                                    name="admin_notifications[recipients][__index__][email]"
                                                    placeholder=" "
                                                    autocomplete="off"
                                                    data-repeater-email
                                                >
                                                <label class="account-input__label" for="admin-notification-__index__">Correo electrónico</label>
                                            </div>
                                        </div>
                                        <div class="account-field account-field--checkbox">
                                            <label class="account-checkbox account-checkbox--center" for="admin-notification-bcc-__index__">
                                                <input
                                                    type="checkbox"
                                                    id="admin-notification-bcc-__index__"
                                                    name="admin_notifications[recipients][__index__][bcc]"
                                                    value="1"
                                                    data-repeater-bcc
                                                >
                                                <span>Enviar en copia oculta</span>
                                            </label>
                                        </div>
                                        <div class="account-repeater__actions">
                                            <button
                                                type="button"
                                                class="account-button account-button--ghost account-repeater__remove"
                                                data-repeater-remove
                                            >
                                                <?php echo Svg::icon('close', 'account-button__icon'); ?>
                                                <span>Eliminar</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <div class="account-repeater__footer">
                                    <button
                                        type="button"
                                        class="account-button account-button--ghost account-repeater__add"
                                        data-repeater-add
                                    >
                                        <?php echo Svg::icon('plus', 'account-button__icon'); ?>
                                        <span>Añadir dirección de correo</span>
                                    </button>
                                </div>
                            </div>
                            <div class="account-field account-field--reply">
                                <div class="account-input-container">
                                    <input
                                        type="email"
                                        id="admin-reply-to-email"
                                        class="account-input"
                                        name="admin_notifications[reply_to]"
                                        value="<?php echo esc_attr($admin_reply_to_email); ?>"
                                        placeholder=" "
                                        autocomplete="off"
                                    >
                                    <label class="account-input__label" for="admin-reply-to-email">Dirección de respuesta</label>
                                </div>
                            </div>
                        </form>
                    <?php else : ?>
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
                    <?php endif; ?>
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

        <?php if ($is_commercial_account && ! $is_admin_account) : ?>
        <article id="account-clients" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('clients', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <h2>Clientes asignados</h2>
                    <p><?php echo esc_html__('Consulta los profesionales que tienes asignados y contacta con ellos rápidamente.', 'garantias-online-360vo'); ?></p>
                </div>
            </header>

            <?php if (! empty($clients)) : ?>
                <div class="account-card-grid account-card-grid--clients">
                    <?php foreach ($clients as $client) : ?>
                        <?php
                        $client_avatar = $client['profile_image']['url'] ?? '';
                        $client_company = trim((string) ($client['company_name'] ?? ''));
                        $client_username = trim((string) ($client['username'] ?? ''));
                        $client_phone = trim((string) ($client['phone'] ?? ''));
                        $client_email = sanitize_email((string) ($client['email'] ?? ''));
                        $client_contact = trim((string) ($client['contact_name'] ?? ''));
                        if ($client_contact !== '') {
                            $client_avatar_alt = sprintf(__('Avatar de %s', 'garantias-online-360vo'), $client_contact);
                        } elseif ($client_company !== '') {
                            $client_avatar_alt = sprintf(__('Logo de %s', 'garantias-online-360vo'), $client_company);
                        } else {
                            $client_avatar_alt = __('Avatar de cliente', 'garantias-online-360vo');
                        }
                        ?>
                        <div class="account-card account-clients__item">
                            <div class="account-clients__header">
                                <div class="account-clients__avatar" aria-hidden="true">
                                    <?php if ($client_avatar) : ?>
                                        <img
                                            src="<?php echo esc_url($client_avatar); ?>"
                                            alt="<?php echo esc_attr($client_avatar_alt); ?>"
                                            loading="lazy"
                                            width="64"
                                            height="64"
                                        >
                                    <?php else : ?>
                                        <?php echo Svg::icon('person', 'account-clients__avatar-icon'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="account-clients__identity">
                                    <span class="account-clients__company">
                                        <?php
                                        $client_display_name = $client_company !== ''
                                            ? $client_company
                                            : ($client_contact !== '' ? $client_contact : __('Cliente sin nombre', 'garantias-online-360vo'));
                                        echo esc_html($client_display_name);
                                        ?>
                                    </span>
                                    <?php if ($client_username !== '') : ?>
                                        <span class="account-clients__username"><?php echo esc_html($client_username); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($client_phone !== '' || $client_email !== '') : ?>
                                <div class="account-clients__actions">
                                    <?php if ($client_phone !== '') : ?>
                                        <a
                                            class="account-clients__action"
                                            href="tel:<?php echo esc_attr($formatPhoneHref($client_phone)); ?>"
                                        >
                                            <?php echo Svg::icon('phone', 'account-clients__action-icon'); ?>
                                            <span><?php echo esc_html($client_phone); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($client_email !== '') : ?>
                                        <a
                                            class="account-clients__action"
                                            href="mailto:<?php echo esc_attr($client_email); ?>"
                                        >
                                            <?php echo Svg::icon('email', 'account-clients__action-icon'); ?>
                                            <span><?php echo esc_html($client_email); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <p class="account-clients__empty"><?php echo esc_html__('Sin datos de contacto disponibles.', 'garantias-online-360vo'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="account-card">
                    <p class="account-card__empty"><?php echo esc_html__('Aún no tienes clientes asignados.', 'garantias-online-360vo'); ?></p>
                </div>
            <?php endif; ?>
        </article>
        <?php endif; ?>

        <?php
            $selected_payment_method = $payments['selected_method'] ?? 'transferencia';
            $sepa_info               = is_array($payments['sepa'] ?? null) ? $payments['sepa'] : [];
            $sepa_status_label       = $sepa_info['status_label'] ?? 'Sin información del mandato';
            $sepa_status_variant     = $sepa_info['status_variant'] ?? 'info';
            $sepa_locked             = (bool) ($sepa_info['locked'] ?? false);
            $sepa_requested          = (bool) ($sepa_info['requested'] ?? false);
            $sepa_fields             = is_array($sepa_info['fields'] ?? null) ? $sepa_info['fields'] : [];
            $method_labels           = [
                'domiciliacion' => 'Domiciliación bancaria',
                'transferencia' => 'Transferencia bancaria',
            ];
            $current_method_label    = $method_labels[$selected_payment_method] ?? $method_labels['transferencia'];
            if ($sepa_locked) {
                $activation_state = 'locked';
            } elseif ($sepa_requested) {
                $activation_state = 'requested';
            } else {
                $activation_state = $selected_payment_method === 'domiciliacion' ? 'enabled' : 'disabled';
            }
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
            $pending_document = is_array($sepa_documents['pending'] ?? null) ? $sepa_documents['pending'] : [];
            $pending_document_available = isset($pending_document['hash']) && $pending_document['hash'] !== '';
            $signed_document  = is_array($sepa_documents['signed'] ?? null) ? $sepa_documents['signed'] : [];
            $signed_document_available = isset($signed_document['hash']) && $signed_document['hash'] !== '';
            $pending_download_url = $pending_document_available ? esc_url($pending_document['url'] ?? '') : '';
            $pending_download_label = $pending_document_available && ! empty($pending_document['filename'])
                ? (string) $pending_document['filename']
                : __('Mandato SEPA pendiente', 'garantias-online-360vo');
            $signed_document_url = $signed_document_available ? esc_url($signed_document['url'] ?? '') : '';
            $sepa_upload_default_label = __('Sube el mandato SEPA firmado (PDF)', 'garantias-online-360vo');
            $sepa_upload_label = $sepa_upload_default_label;
            if ($signed_document_available && ! empty($signed_document['filename'])) {
                $sepa_upload_label = (string) $signed_document['filename'];
            }
            $has_generated_mandate = $pending_document_available || $signed_document_available || ($sepa_info['status'] !== null);

        ?>
        <?php if ((! $is_commercial_account || $is_admin_account) && ! $is_individual_account && ! $is_director_account) : ?>
        <article id="account-payments" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('payment', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <div class="account-section__title">
                        <h2>Pagos</h2>
                        <?php if (! $is_admin_account) : ?>
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
                        <?php endif; ?>
                    </div>
                    <p>
                        <?php if ($is_admin_account) : ?>
                            Consulta la información bancaria disponible para las transferencias.
                        <?php else : ?>
                            Elige cómo prefieres abonar tus garantías y prepara tu domiciliación bancaria cuando quieras.
                        <?php endif; ?>
                    </p>
                </div>
            </header>
            <?php if (! $is_admin_account) : ?>
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
            <?php endif; ?>
            <div class="account-card-grid account-card-grid--payments">
                <?php if ($is_admin_account) : ?>
                    <div class="account-card account-card--payments-summary account-card--admin-transfer">
                        <h3>Configurar transferencias</h3>
                        <p class="account-card__status">Cuenta bancaria utilizada para los cobros por transferencia.</p>
                        <div class="account-field account-field--with-icon">
                            <div class="account-input-wrapper account-input-wrapper--floating">
                                <span class="account-input-wrapper__icon" aria-hidden="true">
                                    <?php echo Svg::icon('iban', 'account-input-wrapper__svg'); ?>
                                </span>
                                <div class="account-input-container">
                                    <input
                                        type="text"
                                        id="account-transfer-iban"
                                        class="account-input"
                                        name="admin_transfer_iban"
                                        value="<?php echo esc_attr($admin_transfer_iban); ?>"
                                        placeholder=" "
                                        autocomplete="off"
                                    >
                                    <label class="account-input__label" for="account-transfer-iban">Cuenta de destino</label>
                                </div>
                            </div>
                            <p class="account-field__hint">Introduce el IBAN al que se deberán realizar las transferencias.</p>
                        </div>
                        <?php if ($admin_transfer_iban === '') : ?>
                            <p class="account-card__note">No hay una cuenta configurada actualmente.</p>
                        <?php endif; ?>
                    </div>
                    <div class="account-card account-card--payments-detail account-card--creditor">
                        <div class="account-card__header">
                            <h3>Datos del acreedor</h3>
                        </div>
                        <p class="account-card__empty">Sin datos disponibles por el momento.</p>
                    </div>
                <?php else : ?>
                    <div
                        class="account-card account-card--payments-summary"
                        data-payment-activation
                        data-state="<?php echo esc_attr($activation_state); ?>"
                    >
                        <h3>Configura tu método de pago</h3>
                        <p class="account-card__status">
                            Método de pago actual: <strong><?php echo esc_html($current_method_label); ?></strong>
                        </p>
                        <?php if ($sepa_requested && ! $sepa_locked) : ?>
                            <p class="account-card__status account-card__status--sepa">
                                <span class="account-card__status-icon" aria-hidden="true"><?php echo Svg::icon('info', 'account-card__status-svg'); ?></span>
                                <span>Estado domiciliación bancaria: <strong><?php echo esc_html__('Pendiente de firma', 'garantias-online-360vo'); ?></strong></span>
                            </p>
                        <?php else : ?>
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
                        <?php endif; ?>
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
                        <?php
                            $sepa_help_default_message   = esc_html__('Completa los campos del mandato y selecciona «Generar SEPA». Te enviaremos el documento listo para firmar y devolverlo a 360VO.', 'garantias-online-360vo');
                            $sepa_help_requested_message = esc_html__('Descarga, firma y devuelve el mandato SEPA para que podamos activar la domiciliación bancaria en tu cuenta.', 'garantias-online-360vo');
                            $sepa_help_message           = $sepa_requested && ! $sepa_locked
                                ? $sepa_help_requested_message
                                : $sepa_help_default_message;
                        ?>
                        <div
                            class="account-help account-help--hidden"
                            id="account-payments-sepa-help"
                            hidden
                            role="region"
                            aria-live="polite"
                        >
                            <div class="account-help__body">
                                <p><?php echo $sepa_help_message; ?></p>
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
                        <?php elseif ($sepa_requested) : ?>
                            <div
                                class="account-sepa-request"
                                data-payment-state="requested"
                                <?php echo $activation_state === 'requested' ? '' : 'hidden aria-hidden="true"'; ?>
                            >
                                <div class="account-sepa-request__actions">
                                    <?php if ($pending_download_url !== '') : ?>
                                        <div class="account-sepa-request__action">
                                            <p class="account-sepa-request__step"><span>1.</span> <?php echo esc_html__('Descarga el documento', 'garantias-online-360vo'); ?></p>
                                            <a
                                                class="account-sepa-request__download"
                                                href="<?php echo esc_url($pending_download_url); ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <span class="account-sepa-request__download-icon" aria-hidden="true"><?php echo Svg::icon('pdf', 'account-sepa-request__download-svg'); ?></span>
                                                <span><?php echo esc_html($pending_download_label); ?></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="account-sepa-request__action">
                                        <p class="account-sepa-request__step"><span>2.</span> <?php echo esc_html__('Súbelo firmado', 'garantias-online-360vo'); ?></p>
                                        <div class="account-sepa-request__upload">
                                            <div class="account-upload account-upload--document">
                                                <div
                                                    class="file-upload file-upload--document"
                                                    id="account-sepa-signed-upload"
                                                    data-default-label="<?php echo esc_attr($sepa_upload_default_label); ?>"
                                                    data-document-upload
                                                    data-document-type="sepa_signed"
                                                >
                                                <div class="file-label" data-document-label><?php echo esc_html($sepa_upload_label); ?></div>
                                                <p class="file-hint">Formato admitido: PDF (máx. 5MB)</p>
                                                <input
                                                    type="file"
                                                    id="account-sepa-signed"
                                                    class="file-input"
                                                    name="account_sepa_signed"
                                                    accept=".pdf"
                                                >
                                                <div
                                                    class="file-preview file-preview--document"
                                                    data-document-preview
                                                    <?php echo $signed_document_url === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                                >
                                                    <div
                                                        class="file-document"
                                                        data-document-body
                                                        <?php echo $signed_document_url === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                                    >
                                                        <span class="file-document__icon" aria-hidden="true">
                                                            <?php echo Svg::icon('pdf', 'file-document__svg'); ?>
                                                        </span>
                                                        <div class="file-document__meta">
                                                            <p class="file-document__name" data-document-name><?php echo esc_html($sepa_upload_label); ?></p>
                                                            <p class="file-document__size" data-document-size hidden aria-hidden="true"></p>
                                                            <a
                                                                class="file-document__link"
                                                                data-document-link
                                                                href="<?php echo esc_url($signed_document_url); ?>"
                                                                <?php echo $signed_document_url === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                                                target="_blank"
                                                                rel="noopener"
                                                            >
                                                                <?php echo esc_html__('Ver documento', 'garantias-online-360vo'); ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <p
                                                        class="file-document__placeholder"
                                                        data-document-placeholder
                                                        <?php echo $signed_document_url !== '' ? 'hidden aria-hidden="true"' : ''; ?>
                                                    >
                                                        <?php echo esc_html__('No se ha seleccionado ningún archivo.', 'garantias-online-360vo'); ?>
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="file-remove"
                                                    data-document-remove
                                                    <?php echo $signed_document_url === '' ? 'hidden' : ''; ?>
                                                >
                                                    <?php echo esc_html__('Eliminar archivo', 'garantias-online-360vo'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                <?php endif; ?>
            </div>
        </article>
        <?php endif; ?>

        <?php if ((! $is_commercial_account || $is_admin_account) && ! $is_individual_account && ! $is_director_account) : ?>
        <article id="account-documents" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('check_shield', 'account-section__icon'); ?>
                <div class="account-section__content">
                    <div class="account-section__title">
                        <h2>Certificados</h2>
                        <?php if (! $is_admin_account) : ?>
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
                        <?php endif; ?>
                    </div>
                    <p>
                        <?php if ($is_admin_account) : ?>
                            Gestiona la documentación que acompaña a los certificados.
                        <?php else : ?>
                            Prepara la firma y el sello que incluiremos en tus certificados.
                        <?php endif; ?>
                    </p>
                </div>
            </header>
            <?php if (! $is_admin_account) : ?>
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
            <?php endif; ?>
            <div class="account-card-grid">
                <?php if ($is_admin_account) : ?>
                    <?php
                    $procedure_label = $procedure_default_label;
                    if (! empty($admin_claim_document['filename']) && is_string($admin_claim_document['filename'])) {
                        $procedure_label = esc_html($admin_claim_document['filename']);
                    } elseif (! empty($admin_claim_document['title']) && is_string($admin_claim_document['title'])) {
                        $procedure_label = esc_html($admin_claim_document['title']);
                    }
                    $procedure_url = isset($admin_claim_document['url']) ? (string) $admin_claim_document['url'] : '';
                    ?>
                    <div class="account-card account-card--certificates account-card--procedure">
                        <h3>Procedimiento de reclamación</h3>
                        <div class="account-upload account-upload--document">
                            <div
                                class="file-upload file-upload--document"
                                id="procedure-upload"
                                data-default-label="<?php echo esc_attr($procedure_default_label); ?>"
                                data-document-upload
                                data-document-type="claim_procedure"
                            >
                                <div class="file-label" data-document-label><?php echo $procedure_label; ?></div>
                                <p class="file-hint">Formatos admitidos: PDF, DOC, JPG, PNG (máx. 10MB)</p>
                                <input
                                    type="file"
                                    id="procedure"
                                    class="file-input"
                                    name="admin_claim_document"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                >
                                <div class="file-preview file-preview--document" data-document-preview>
                                    <div
                                        class="file-document"
                                        data-document-body
                                        <?php echo $procedure_url === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                    >
                                        <span class="file-document__icon" aria-hidden="true">
                                            <?php echo Svg::icon('pdf', 'file-document__svg'); ?>
                                        </span>
                                        <div class="file-document__meta">
                                            <p class="file-document__name" data-document-name><?php echo $procedure_label; ?></p>
                                            <p
                                                class="file-document__size"
                                                data-document-size
                                                <?php echo $admin_claim_document_size === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                            >
                                                <?php echo esc_html($admin_claim_document_size); ?>
                                            </p>
                                            <a
                                                class="file-document__link"
                                                data-document-link
                                                href="<?php echo esc_url($procedure_url); ?>"
                                                <?php echo $procedure_url === '' ? 'hidden aria-hidden="true"' : ''; ?>
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Ver documento
                                            </a>
                                        </div>
                                    </div>
                                    <p
                                        class="file-document__placeholder"
                                        data-document-placeholder
                                        <?php echo $procedure_url !== '' ? 'hidden aria-hidden="true"' : ''; ?>
                                    >
                                        No se ha seleccionado ningún archivo.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="file-remove"
                                    data-document-remove
                                    <?php echo $procedure_url === '' ? 'hidden' : ''; ?>
                                >
                                    <?php echo esc_html__('Eliminar archivo', 'garantias-online-360vo'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
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
                                        <p
                                            class="file-warning"
                                            data-certificates-warning
                                            hidden
                                            aria-hidden="true"
                                            aria-live="polite"
                                            role="alert"
                                        >
                                            Necesitas subir tanto la firma como el sello si quieres que tus certificados se generen ya firmados.
                                        </p>
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
                <?php endif; ?>
            </div>
        </article>
        <?php endif; ?>
        <?php if ($is_professional_account) : ?>
            <article id="account-workshop" class="account-section" tabindex="-1">
                <header class="account-section__header">
                    <?php echo Svg::icon('taller', 'account-section__icon'); ?>
                    <div class="account-section__content">
                        <h2>Taller</h2>
                        <p>Indica si trabajas con un taller propio o asociado y revisa sus datos principales.</p>
                    </div>
                </header>
                <div class="account-card-grid account-card-grid--workshop">
                    <div class="account-card account-card--form account-card--workshop">
                        <h3>Datos del taller</h3>
                        <div class="account-field account-field--checkbox">
                            <label class="account-checkbox account-checkbox--center" for="account-workshop-toggle">
                                <input
                                    type="checkbox"
                                    id="account-workshop-toggle"
                                    name="account_workshop[has_workshop]"
                                    value="1"
                                    data-workshop-toggle
                                    aria-controls="account-workshop-details"
                                    aria-expanded="<?php echo $workshop_has ? 'true' : 'false'; ?>"
                                    <?php checked($workshop_has); ?>
                                >
                                <span>Dispongo de taller propio / asociado</span>
                            </label>
                        </div>
                        <form
                            class="account-form account-form--workshop"
                            action="#"
                            method="post"
                            novalidate
                            id="account-workshop-details"
                            data-workshop-details
                            <?php echo $workshop_has ? '' : 'hidden aria-hidden="true"'; ?>
                        >
                            <?php foreach ($workshop_fields as $field) : ?>
                                <?php
                                $field_value   = (string) ($workshop[$field['key']] ?? '');
                                $field_classes = ['account-field', 'account-form__field'];
                                if (! empty($field['class'])) {
                                    $field_classes[] = $field['class'];
                                }
                                $autocomplete = isset($field['autocomplete']) ? (string) $field['autocomplete'] : '';
                                $inputmode    = isset($field['inputmode']) ? (string) $field['inputmode'] : '';
                                ?>
                                <div class="<?php echo esc_attr(implode(' ', $field_classes)); ?>">
                                    <div class="account-input-container">
                                        <input
                                            type="<?php echo esc_attr($field['type']); ?>"
                                            id="<?php echo esc_attr($field['id']); ?>"
                                            class="account-input"
                                            name="account_workshop[<?php echo esc_attr($field['key']); ?>]"
                                            value="<?php echo esc_attr($field_value); ?>"
                                            placeholder=" "
                                            <?php echo $autocomplete !== '' ? 'autocomplete="' . esc_attr($autocomplete) . '"' : ''; ?>
                                            <?php echo $inputmode !== '' ? 'inputmode="' . esc_attr($inputmode) . '"' : ''; ?>
                                        >
                                        <label class="account-input__label" for="<?php echo esc_attr($field['id']); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </article>
        <?php endif; ?>
    </section>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_account_page')
);
