<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Support\NotificationEmailResolver;

$is_auth_template = ! empty($is_auth_page);

// Calculamos botón de perfil y avatar
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$display_name = $current_user->display_name ?: $current_user->user_login;
$notification_email = $current_user->user_email;

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
    <?php if (! empty($is_list_page)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/mis_garantias.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <?php if (! empty($is_account_page)) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/account.min.css', GARANTIAS360VO__FILE__)); ?>">
    <?php endif; ?>
    <script src="<?php echo esc_url(plugins_url(
                        'assets/js/global.min.js',
                        GARANTIAS360VO__FILE__
                    )); ?>" defer></script>
</head>

<body class="<?php echo $is_auth_template ? 'body--auth' : ''; ?>">
    <?php if (! $is_auth_template) : ?>
    <header class="top-bar" style="view-transition-name: header">
        <div class="top-bar__wrapper">
            <button class="top-bar__hamburger" aria-label="Menú">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <div class="top-bar__logo-container">
                <a href="<?php echo esc_url($home_destination); ?>">
                    <img src="<?php echo esc_url(
                                    plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__)
                                ); ?>"
                        alt="360Vo Garantías Online"
                        class="top-bar__logo">
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
                                <span class="profile-menu__name"><?php echo esc_html($display_name); ?></span>
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
    <div class="container <?php echo $is_auth_template ? 'container--auth' : ''; ?>">
        <div class="main-grid <?php echo $is_auth_template ? 'main-grid--auth' : ''; ?>">
