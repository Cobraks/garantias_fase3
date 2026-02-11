<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;

$is_auth_template = ! empty($is_auth_page);
$is_breakdown_detail_template = ! empty($is_breakdown_detail_page);

// Calculamos botón de perfil y avatar
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$display_name = UserProfileResolver::get_personal_name($current_user);
$notification_email = $current_user->user_email;
$profile_person_name = $display_name;
$profile_company_name = '';

if ($current_user instanceof \WP_User
    && in_array('go_profesional', (array) $current_user->roles, true)
) {
    $labels = UserProfileResolver::get_vendor_labels($current_user_id);
    if (! empty($labels['company_name']) && $labels['company_name'] !== $labels['personal_name']) {
        $profile_company_name = $labels['company_name'];
    }
    if (! empty($labels['personal_full_name'])) {
        $profile_person_name = $labels['personal_full_name'];
    } elseif (! empty($labels['personal_name'])) {
        $profile_person_name = $labels['personal_name'];
    }
}

if ($current_user_id && class_exists(NotificationEmailResolver::class)) {
    $resolved_email = NotificationEmailResolver::resolve($current_user_id);
    if (! empty($resolved_email)) {
        $notification_email = $resolved_email;
    }
}

$avatar_id = function_exists('get_field')
    ? get_field('profile_image', 'user_' . $current_user_id)
    : false;

$avatar_html = $avatar_id
    ? sprintf(
        '<img src="%s" alt="%s" class="top-bar__profile-icon">',
        esc_url(wp_get_attachment_image_url($avatar_id, [44, 44])),
        esc_attr__('Mi perfil', 'garantias-online-360vo')
    )
    : Svg::icon('user', 'top-bar__profile-icon');

$profile_menu_id = 'profile-menu-' . $current_user_id;
$profile_button = sprintf(
    '<button type="button" class="top-bar__profile-link" aria-label="%1$s" aria-haspopup="true" aria-expanded="false" aria-controls="%2$s">%3$s</button>',
    esc_attr(sprintf(
        /* translators: %s: display name */
        __('Abrir menú de cuenta de %s', 'garantias-online-360vo'),
        $display_name
    )),
    esc_attr($profile_menu_id),
    $avatar_html
);

$account_url = home_url('/garantias-online/mi-cuenta/');
$is_admin_user = current_user_can('manage_options');
$home_destination = $is_admin_user
    ? home_url('/garantias-online')
    : home_url('/garantias-online/mis-garantias/');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?php echo esc_html(get_bloginfo('name')); ?> – Garantías Online</title>
    <script>
        (function () {
            var storageKey = 'go_theme';
            var theme = null;
            try {
                theme = localStorage.getItem(storageKey);
            } catch (error) {
                theme = null;
            }

            if (theme !== 'dark' && theme !== 'light') {
                try {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                } catch (error) {
                    theme = 'light';
                }
            }

            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= esc_url(plugins_url('assets/css/global.min.css', GARANTIAS360VO__FILE__)); ?>">


    <?php if (! empty($is_dashboard_page)) : ?>
        <link
            rel="stylesheet"
            href="<?php echo esc_url(plugins_url('assets/css/dashboard.min.css', GARANTIAS360VO__FILE__)); ?>">
        <?php
        $activity_catalog = \GarantiasOnline360VO\ActivityLog\EventCatalog::to_public_catalog();
        $activity_data = [
            'endpoint'            => esc_url_raw(rest_url('go/v1/activity')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'catalog'             => $activity_catalog,
            'guaranteeEditBase'   => esc_url_raw(admin_url('post.php')),
            'currentUser'         => [
                'id'         => get_current_user_id(),
                'canManage'  => current_user_can('manage_options'),
            ],
        ];
        ?>
        <script>
            var go360Activity = <?php echo wp_json_encode($activity_data); ?>;
        </script>
        <script src="<?php echo esc_url(plugins_url('assets/js/dashboard.min.js', GARANTIAS360VO__FILE__)); ?>" defer></script>
    <?php endif; ?>

    <?php if (! empty($is_add_guarantee)) : ?>


        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
        <link
            rel="stylesheet"
            href="<?php echo esc_url(plugins_url('assets/css/nueva_garantia.min.css', GARANTIAS360VO__FILE__)); ?>">


    <?php endif; ?>
    <?php if (! empty($is_register_page)) : ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            rel="stylesheet"
            href="<?php echo esc_url(plugins_url('assets/css/register.min.css', GARANTIAS360VO__FILE__)); ?>">
        <link
            rel="stylesheet"
            href="<?php echo esc_url(plugins_url('assets/css/auth.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <?php if (! empty($is_register_page)) : ?>
        <link
            rel="stylesheet"
            href="<?php echo esc_url(plugins_url('assets/css/nueva_garantia.min.css', GARANTIAS360VO__FILE__)); ?>">
        <?php
        $register_data = [
            'rest' => [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
        ];
        ?>
        <script>
            window.go360Register = <?php echo wp_json_encode($register_data); ?>;
        </script>
    <?php endif; ?>
    <?php if (! empty($is_list_page) || ! empty($is_breakdowns_page)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/mis_garantias.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <?php if (! empty($is_clients_page)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/mis_garantias.min.css', GARANTIAS360VO__FILE__)); ?>">
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/clientes.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <?php if ($is_breakdown_detail_template) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/averias.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <?php if (! empty($is_account_page)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/account.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <script src="<?php echo esc_url(plugins_url(
                        'assets/js/global.min.js',
                        GARANTIAS360VO__FILE__
                    )); ?>" defer></script>
</head>

    <?php
    $body_classes = [];
    if ($is_auth_template) {
        $body_classes[] = 'body--auth';
    }
    if (! empty($is_clients_page)) {
        $body_classes[] = 'body--clients';
    }
    if (! empty($is_list_page) || ! empty($is_breakdowns_page)) {
        $body_classes[] = 'body--guarantees';
    }
    if ($is_breakdown_detail_template) {
        $body_classes[] = 'body--averia-detail';
    }
    ?>
<body class="<?php echo esc_attr(implode(' ', $body_classes)); ?>">
    <?php if (! $is_auth_template) : ?>
    <header class="top-bar" style="view-transition-name: header">
        <div class="top-bar__wrapper">
            <button class="top-bar__hamburger" aria-label="Menú">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <div class="top-bar__logo-container">
                <a href="<?php echo esc_url($home_destination); ?>" class="top-bar__logo-link">
                    <svg
                        class="top-bar__logo top-bar__logo--svg"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 449.1 141.8"
                        role="img"
                        aria-label="360Vo Garantías Online"
                        focusable="false">
                        <g class="top-bar__logo-vo">
                            <path d="M415.2,44.1c6.3,9.1,4,21.7,4.4,32.2c0.3,12-0.3,25.6-13.3,30.5c-3.7,1.4-7.7,1.7-11.6,1.9   c-5.6,0.2-11.2,0.2-16.8,0.1c-14.9,0.2-26.3-4-27.9-20.4c-0.7-7.8-0.4-15.5-0.5-23.3c0-8,0.2-17.3,6.2-23.3   c6.1-6.4,15.9-6.7,24.2-6.7c12.1,0.3,27.4-2.2,35.2,8.9L415.2,44.1z M391.1,50.5c-4.5,0-9.2,0-13.7,0c-2.7,0-5.7,0-8.1,1.4   c-4.5,2.6-3.9,8.7-4,13.3c0,5.5,0,11.1,0,16.7c-0.2,9,3.6,11.7,12.2,11.5c4.8,0,9.5,0.1,14.3,0c6.5,0.1,11.4-1.4,11.8-8.8   c0.2-7.8,0.1-15.5,0.1-23.4c0.1-8.8-4.3-10.9-12.5-10.7L391.1,50.5z"></path>
                            <path d="M308.6,93.3c-0.4-1-21.6-58.7-21.6-58.7s-16,0-16.6,0c0,0,0,0,0,0c0.2,0.7,27.3,74.5,27.4,74.6   c4.4,0,21.2,0.1,21.2,0.1l27.8-74.5h-16.9C323.8,51.2,309.4,90.9,308.6,93.3z"></path>
                        </g>
                        <g class="top-bar__logo-360">
                            <path d="M237.4,44.1c6.3,9.1,4,21.7,4.4,32.2c0.3,12-0.3,25.6-13.3,30.5c-3.7,1.4-7.7,1.7-11.6,1.9   c-5.6,0.2-11.2,0.2-16.8,0.1c-14.9,0.2-26.3-4-27.9-20.4c-0.7-7.8-0.4-15.5-0.5-23.3c0-8,0.2-17.3,6.2-23.3   c6.1-6.4,15.9-6.7,24.2-6.7c12.1,0.3,27.4-2.2,35.2,8.9L237.4,44.1z M213.3,50.5c-4.5,0-9.2,0-13.7,0c-2.7,0-5.7,0-8.1,1.4   c-4.5,2.6-3.9,8.7-4,13.3c0,5.5,0,11.1,0,16.7c-0.2,9,3.6,11.7,12.2,11.5c4.8,0,9.5,0.1,14.3,0c6.5,0.1,11.4-1.4,11.8-8.8   c0.2-7.8,0.1-15.5,0.1-23.4c0.1-8.8-4.3-10.9-12.5-10.7L213.3,50.5z"></path>
                            <path d="M148.5,50.9c0,0-23.7,0-26.1,0c-2.4,0-4.5,2-4.7,4.6c-0.2,2.9,0,5.8,0,8.9c3.1,0,26.5,0,30.8,0   c7.4,0,14.6,5.5,15.3,14.7c0.5,5,0.4,10.1,0,15.2c-0.3,7.2-5.3,12-12.2,13.8c-9.9,0.7-20,1.1-29.9,0.6c-2.4-0.2-4.7-0.2-7.1-0.5   c-3.8-1.1-7.6-2.9-10-6.2c-1.8-3-2.7-6.3-2.7-9.9c0-13.3-0.1-26.5,0-39.8c0-13.1,9-16.8,18.5-16.8h43.3L148.5,50.9z M117.6,87   c0,5.3,3.1,6.4,5.1,6.4c2,0,18.3,0,20.8,0c2.5,0,4.9-0.7,4.9-3.9s0-0.1,0-5s-3.4-4.9-10.1-4.9c-4.3,0-20.7,0-20.7,0   S117.6,85.9,117.6,87z"></path>
                            <path d="M77.1,108.7c-13,0-44.9,0-44.9,0l15.5-15.2c0,0,23.1,0,26.3,0s4.2-1.4,4.3-4.2c0,0,0-6.3,0-9.7   l-46,0c0.5-0.6,15.2-15.2,15.2-15.2s15.4,0,22.7-0.1c4.3,0.1,8.1-1.7,8.3-6.5c0.6-5.6-3.2-7-8-7.2c-7.5,0-33.7,0-38.4,0   C37.3,45.3,47.6,35,47.6,35s20.7,0,30,0S93.1,42,94,49c0.3,2.8,0.3,7,0.3,9.5c0,1.1-0.4,2.1-1.1,2.9c-4.6,5.5-9,10.1-9.5,10.6h10.5   c0,0.4-0.4,24-0.4,24S92.7,108.7,77.1,108.7z"></path>
                        </g>
                    </svg>
                </a>
            </div>
            <?php if (! empty($is_dashboard_page)) : ?>
                <div class="dashboard__filters" style="view-transition-name: filtros">
                    <div class="dashboard__filter-group">
                        <select class="dashboard__filter-select" id="filter-category" aria-label="Categoría de garantías">
                            <option value="all">Todas las garantías</option>
                            <option value="professionals">Profesionales</option>
                            <option value="management">Gestorías</option>
                            <option value="individuals">Particulares</option>
                            <option value="commercials">Comerciales</option>
                        </select>

                        <select class="dashboard__filter-select" id="filter-specific" style="display: none;" aria-label="Filtro específico">
                            <!-- Opciones dinámicas se llenarán con JavaScript -->
                            <option value="">Seleccione un profesional</option>
                        </select>

                        <select class="dashboard__filter-select" id="filter-month" aria-label="Mes">
                            <option value="all">Todos los meses</option>
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7" selected>Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>

                        <select class="dashboard__filter-select" id="filter-year" aria-label="Año">
                            <option>Todos los años</option>
                            <option value="2023">2023</option>
                            <option value="2024">2024</option>
                            <option value="2025" selected>2025</option>
                        </select>
                    </div>
                </div>
            <?php endif; ?>


            <?php if (is_user_logged_in()) : ?>
                <nav class="top-bar__menu">
                    <ul>
                        <?php if ($is_admin_user) : ?>
                            <li class="menu-item button-item">
                                <a href="<?php echo esc_url(home_url('/garantias-online/averias/')); ?>">
                                    <?php echo Svg::icon('car_crash', 'top-bar__icon'); ?>
                                    <?php esc_html_e('Averías', 'garantias-online-360vo'); ?>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo esc_url(home_url('/garantias-online/clientes/')); ?>">
                                    <?php echo Svg::icon('person', 'top-bar__icon'); ?>
                                    <?php esc_html_e('Clientes', 'garantias-online-360vo'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="menu-item">
                            <a href="<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>">
                                <?php echo Svg::icon('shield', 'top-bar__icon'); ?>
                                <?php esc_html_e('Mis Garantías', 'garantias-online-360vo'); ?>
                            </a>
                        </li>
                        <li class="menu-item button-item button-item--nueva-garantia">
                            <a href="<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>" data-reset-draft>
                                <?php echo Svg::icon('new_shield', 'top-bar__icon'); ?>
                                <?php esc_html_e('Nueva Garantía', 'garantias-online-360vo'); ?>
                            </a>
                        </li>
                        <?php if ($is_admin_user) : ?>
                            <li
                                class="menu-item menu-item--notifications"
                                data-admin-notifications
                                data-icon-mark="<?php echo esc_attr(base64_encode(Svg::icon('visibility_off', 'notifications-panel__action-icon'))); ?>"
                                data-icon-mark-read="<?php echo esc_attr(base64_encode(Svg::icon('visibility', 'notifications-panel__action-icon'))); ?>"
                                data-icon-delete="<?php echo esc_attr(base64_encode(Svg::icon('delete', 'notifications-panel__action-icon'))); ?>"
                                data-icon-close="<?php echo esc_attr(base64_encode(Svg::icon('close', 'notifications-modal__close-icon'))); ?>"
                                data-mark-label="<?php echo esc_attr__('Sin leer', 'garantias-online-360vo'); ?>"
                                data-marked-label="<?php echo esc_attr__('Leída', 'garantias-online-360vo'); ?>"
                                data-delete-label="<?php echo esc_attr__('Eliminar', 'garantias-online-360vo'); ?>"
                                data-load-more-label="<?php echo esc_attr__('Cargar más', 'garantias-online-360vo'); ?>"
                                data-loading-label="<?php echo esc_attr__('Cargando…', 'garantias-online-360vo'); ?>"
                                data-view-all-label="<?php echo esc_attr__('Ver todas las notificaciones', 'garantias-online-360vo'); ?>"
                                data-toast-open-label="<?php echo esc_attr__('Ver ahora', 'garantias-online-360vo'); ?>"
                                data-toast-dismiss-label="<?php echo esc_attr__('Descartar', 'garantias-online-360vo'); ?>"
                                data-modal-placeholder="<?php echo esc_attr__('Muy pronto podrás gestionar todas tus notificaciones desde aquí.', 'garantias-online-360vo'); ?>"
                                data-modal-close-label="<?php echo esc_attr__('Cerrar', 'garantias-online-360vo'); ?>"
                            >
                                <button
                                    type="button"
                                    class="top-bar__notifications-button"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    aria-controls="top-bar-notifications-panel"
                                    data-notifications-toggle
                                >
                                    <?php echo Svg::icon('notifications', 'top-bar__icon top-bar__icon--notifications'); ?>
                                    <span class="top-bar__notifications-badge" data-notifications-badge hidden></span>
                                    <span class="screen-reader-text"><?php esc_html_e('Abrir bandeja de notificaciones', 'garantias-online-360vo'); ?></span>
                                </button>
                                <div
                                    class="notifications-panel"
                                    id="top-bar-notifications-panel"
                                    role="region"
                                    aria-live="polite"
                                    aria-hidden="true"
                                    data-notifications-panel
                                >
                                    <div class="notifications-panel__header">
                                        <div class="notifications-panel__header-main">
                                            <h4 class="notifications-panel__title"><?php esc_html_e('Notificaciones', 'garantias-online-360vo'); ?></h4>
                                        </div>
                                        <button type="button" class="notifications-panel__mark" data-notifications-mark-all>
                                            <?php esc_html_e('Marcar todo como leído', 'garantias-online-360vo'); ?>
                                        </button>
                                    </div>
                                    <div class="notifications-panel__content" data-notifications-scroll>
                                        <p class="notifications-panel__empty" data-notifications-empty hidden>
                                            <?php esc_html_e('No tienes notificaciones nuevas.', 'garantias-online-360vo'); ?>
                                        </p>
                                        <ul class="notifications-panel__list" data-notifications-list></ul>
                                        <button type="button" class="notifications-panel__load-more" data-notifications-load-more hidden>
                                            <?php esc_html_e('Cargar más', 'garantias-online-360vo'); ?>
                                        </button>
                                    </div>
                                    <div class="notifications-panel__footer">
                                        <div class="notifications-panel__footer-controls">
                                            <div class="notifications-panel__toast-toggle" data-notifications-toast-toggle>
                                                <span class="notifications-panel__toast-label"><?php esc_html_e('Notif. emergentes', 'garantias-online-360vo'); ?></span>
                                                <div class="notifications-panel__toast-options">
                                                    <button type="button" class="notifications-panel__toast-option" data-toast-option="on" aria-pressed="false">
                                                        <?php esc_html_e('Activadas', 'garantias-online-360vo'); ?>
                                                    </button>
                                                    <button type="button" class="notifications-panel__toast-option" data-toast-option="off" aria-pressed="false">
                                                        <?php esc_html_e('Desactivadas', 'garantias-online-360vo'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" class="notifications-panel__view-all" data-notifications-view-all>
                                                <?php esc_html_e('Ver todas las notificaciones', 'garantias-online-360vo'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="notifications-toast" data-notifications-toast hidden></div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php $tiene_foto = false; ?>
                <div class="top-bar__profile">
                    <?php echo $profile_button; ?>
                    <div
                        class="top-bar__profile-menu"
                        id="<?php echo esc_attr($profile_menu_id); ?>"
                        role="menu"
                        aria-label="<?php esc_attr_e('Menú de cuenta', 'garantias-online-360vo'); ?>"
                        aria-hidden="true"
                    >
                        <div class="profile-menu__header">
                            <div class="profile-menu__info">
                                <?php if ($profile_company_name !== '') : ?>
                                    <span class="profile-menu__company"><?php echo esc_html($profile_company_name); ?></span>
                                <?php else : ?>
                                    <span class="profile-menu__name"><?php echo esc_html($profile_person_name); ?></span>
                                <?php endif; ?>
                                <?php if (! empty($notification_email)) : ?>
                                    <span class="profile-menu__email"><?php echo esc_html($notification_email); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-menu__list" role="none">
                            <a
                                href="<?php echo esc_url($account_url); ?>"
                                class="profile-menu__item"
                                role="menuitem"
                            >
                                <?php echo Svg::icon('settings', 'profile-menu__icon'); ?>
                                <span class="profile-menu__text"><?php esc_html_e('Mi cuenta', 'garantias-online-360vo'); ?></span>
                            </a>
                            <?php if ($is_admin_user) : ?>
                                <a
                                    href="<?php echo esc_url(admin_url('edit.php?post_type=garantia')); ?>"
                                    class="profile-menu__item"
                                    role="menuitem"
                                >
                                    <?php echo Svg::icon('wordpress_icon', 'profile-menu__icon'); ?>
                                    <span class="profile-menu__text"><?php esc_html_e('Escritorio WordPress', 'garantias-online-360vo'); ?></span>
                                </a>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="profile-menu__item profile-menu__item--theme"
                                role="switch"
                                aria-checked="false"
                                data-theme-toggle
                                data-theme-label-off="<?php esc_attr_e('Activar modo oscuro', 'garantias-online-360vo'); ?>"
                                data-theme-label-on="<?php esc_attr_e('Activar modo claro', 'garantias-online-360vo'); ?>"
                                data-theme-status-off="<?php esc_attr_e('Desactivado', 'garantias-online-360vo'); ?>"
                                data-theme-status-on="<?php esc_attr_e('Activado', 'garantias-online-360vo'); ?>"
                                title="<?php esc_attr_e('Activar modo oscuro', 'garantias-online-360vo'); ?>"
                            >
                                <span class="profile-menu__icon profile-menu__icon--theme" aria-hidden="true">
                                    <?php echo Svg::icon('sun', 'profile-menu__theme-icon profile-menu__theme-icon--sun'); ?>
                                    <?php echo Svg::icon('moon', 'profile-menu__theme-icon profile-menu__theme-icon--moon'); ?>
                                </span>
                                <span class="profile-menu__text">
                                    <span class="profile-menu__theme-title"><?php esc_html_e('Modo oscuro', 'garantias-online-360vo'); ?></span>
                                    <span class="profile-menu__theme-status" data-theme-status><?php esc_html_e('Desactivado', 'garantias-online-360vo'); ?></span>
                                </span>
                                <span class="profile-menu__theme-switch" aria-hidden="true">
                                    <span class="profile-menu__theme-thumb"></span>
                                </span>
                            </button>
                        </div>
                        <div class="profile-menu__footer">
                            <a
                                href="<?php echo esc_url(wp_logout_url(home_url('/garantias-online/'))); ?>"
                                class="profile-menu__item profile-menu__item--logout"
                                role="menuitem"
                            >
                                <?php echo Svg::icon('logout', 'profile-menu__icon'); ?>
                                <span class="profile-menu__text"><?php esc_html_e('Cerrar sesión', 'garantias-online-360vo'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif (! empty($is_register_page)) : ?>
                <nav class="top-bar__menu top-bar__menu--register">
                    <ul>
                        <li class="menu-item menu-item--login">
                            <a href="<?php echo esc_url('http://garantas-fase-iii.local/garantias-online/'); ?>" class="top-bar__login-link">
                                <?php echo Svg::icon('login', 'top-bar__icon'); ?>
                                <?php esc_html_e('Inicia sesión', 'garantias-online-360vo'); ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
        <nav class="mobile-menu">
            <ul>
                <?php if ($is_admin_user) : ?>
                    <li class="mobile-menu__item">
                        <a href="<?php echo esc_url(home_url('/garantias-online/clientes/')); ?>">
                            <?php esc_html_e('Clientes', 'garantias-online-360vo'); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="mobile-menu__item">
                    <a href="<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>">

                        <?php esc_html_e('Mis Garantías', 'garantias-online-360vo'); ?>
                    </a>
                </li>
                <li class="mobile-menu__item">
                    <a href="<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>" data-reset-draft>

                        <?php esc_html_e('Nueva Garantía', 'garantias-online-360vo'); ?>
                    </a>
                </li>
            </ul>
        </nav>
    </header>
    <?php endif; ?>
    <?php
    $container_classes = ['container'];
    if ($is_auth_template) {
        $container_classes[] = 'container--auth';
    }
    if (! empty($is_clients_page)) {
        $container_classes[] = 'clients-page';
    }
    if (! empty($is_list_page) || ! empty($is_breakdowns_page)) {
        $container_classes[] = 'guarantees-page';
    }

    $main_grid_classes = ['main-grid'];
    if ($is_auth_template) {
        $main_grid_classes[] = 'main-grid--auth';
    }
    if (! empty($is_clients_page)) {
        $main_grid_classes[] = 'clients-page';
    }
    if (! empty($is_list_page) || ! empty($is_breakdowns_page)) {
        $main_grid_classes[] = 'guarantees-page';
    }
    ?>
    <div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>">
        <div class="<?php echo esc_attr(implode(' ', $main_grid_classes)); ?>">
