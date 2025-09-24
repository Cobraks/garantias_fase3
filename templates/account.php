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
        'label' => 'Gestión de cobros SEPA',
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

$formatPaymentLabel = static function (?bool $status): string {
    if ($status === true) {
        return 'Activo';
    }
    if ($status === false) {
        return 'Pendiente de validar';
    }
    return 'Sin información';
};

$signed = $payments['signed_document']['url'] ?? '';
$pending = $payments['pending_document']['url'] ?? '';
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
            <a class="account-summary__button" href="<?php echo esc_url(admin_url('profile.php')); ?>">
                <?php echo Svg::icon('settings', 'account-summary__button-icon'); ?>
                Configurar datos en WordPress
            </a>
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
                                <li>
                                    <span class="account-contacts__name"><?php echo esc_html($commercial['name']); ?></span>
                                    <?php if (! empty($commercial['email'])) : ?>
                                        <span class="account-contacts__item">
                                            <?php echo Svg::icon('email', 'account-contacts__icon'); ?>
                                            <a href="mailto:<?php echo esc_attr($commercial['email']); ?>"><?php echo esc_html($commercial['email']); ?></a>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (! empty($commercial['phone'])) : ?>
                                        <span class="account-contacts__item">
                                            <?php echo Svg::icon('phone', 'account-contacts__icon'); ?>
                                            <a href="tel:<?php echo esc_attr($formatPhoneHref($commercial['phone'])); ?>"><?php echo esc_html($commercial['phone']); ?></a>
                                        </span>
                                    <?php endif; ?>
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
                    <p>Controla las direcciones a las que enviaremos avisos y certificados.</p>
                </div>
            </header>
            <div class="account-card-grid">
                <div class="account-card">
                    <h3>Correos configurados</h3>
                    <dl class="account-card__list">
                        <div>
                            <dt>Correo de registro</dt>
                            <dd><?php echo esc_html($user['email'] ?? ''); ?></dd>
                        </div>
                        <div>
                            <dt>Notificaciones</dt>
                            <dd><?php echo esc_html($user['notification_email'] ?? ''); ?></dd>
                        </div>
                        <?php if (! empty($user['custom_notification'])) : ?>
                            <div>
                                <dt>Correo alternativo</dt>
                                <dd><?php echo esc_html($user['custom_notification']); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <p class="account-card__note">
                        <?php if ($user['same_as_registration'] === true) : ?>
                            Actualmente las notificaciones se envían a la dirección principal de tu cuenta.
                        <?php elseif ($user['same_as_registration'] === false) : ?>
                            Has definido un correo alternativo para recibir los avisos y copias de certificados.
                        <?php else : ?>
                            Puedes personalizar el correo de notificaciones desde tu perfil.
                        <?php endif; ?>
                    </p>
                    <a class="account-card__cta" href="<?php echo esc_url(admin_url('profile.php')); ?>">
                        <?php echo Svg::icon('settings', 'account-card__cta-icon'); ?>
                        Ajustar preferencias de correo
                    </a>
                </div>
            </div>
        </article>

        <article id="account-payments" class="account-section" tabindex="-1">
            <header class="account-section__header">
                <?php echo Svg::icon('payment', 'account-section__icon'); ?>
                <div>
                    <h2>Gestión de cobros SEPA</h2>
                    <p>Datos bancarios y estado de tu mandato SEPA para domiciliar pagos.</p>
                </div>
            </header>
            <div class="account-card-grid">
                <div class="account-card">
                    <h3>Datos del deudor</h3>
                    <dl class="account-card__list">
                        <?php if (! empty($payments['holder'])) : ?>
                            <div>
                                <dt>Titular</dt>
                                <dd><?php echo esc_html($payments['holder']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($payments['address'])) : ?>
                            <div>
                                <dt>Dirección</dt>
                                <dd><?php echo esc_html($payments['address']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($payments['iban'])) : ?>
                            <div>
                                <dt>IBAN</dt>
                                <dd class="account-card__code"><?php echo esc_html($payments['iban']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($payments['swift'])) : ?>
                            <div>
                                <dt>Swift / BIC</dt>
                                <dd class="account-card__code"><?php echo esc_html($payments['swift']); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($payments['payment_type'])) : ?>
                            <div>
                                <dt>Tipo de cobro</dt>
                                <dd><?php echo esc_html($payments['payment_type']); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="account-card">
                    <h3>Estado de documentación</h3>
                    <dl class="account-card__list">
                        <div>
                            <dt>Situación</dt>
                            <dd><?php echo esc_html($formatPaymentLabel($payments['status'] ?? null)); ?></dd>
                        </div>
                        <?php if ($signed) : ?>
                            <div>
                                <dt>Mandato firmado</dt>
                                <dd>
                                    <a href="<?php echo esc_url($signed); ?>" target="_blank" rel="noopener noreferrer">Descargar documento</a>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($pending) : ?>
                            <div>
                                <dt>Borrador SEPA</dt>
                                <dd>
                                    <a href="<?php echo esc_url($pending); ?>" target="_blank" rel="noopener noreferrer">Descargar documento</a>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <p class="account-card__note">
                        ¿Necesitas actualizar estos datos? Ponte en contacto con tu comercial asignado para solicitar un nuevo mandato.
                    </p>
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
