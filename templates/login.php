<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Auth\AuthController;
use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_embedded   = ! empty($is_embedded);
$is_login_page = ! $is_embedded;
$is_auth_page  = true;

if ($is_login_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page'));
}

$default_redirect = home_url('/garantias-online/');
$redirect_to      = $default_redirect;

$state_token = get_query_var('state');
if ($state_token === '') {
    $state_token = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
}
$state_token = is_scalar($state_token) ? (string) $state_token : '';

$state_data = $state_token !== '' ? AuthController::consume_login_state($state_token) : [];

if (! empty($state_data['redirect_to'])) {
    $redirect_to = $state_data['redirect_to'];
} else {
    $raw_redirect_to = get_query_var('redirect_to');
    if ($raw_redirect_to === '') {
        $raw_redirect_to = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : '';
    }
    if ($raw_redirect_to !== '' && is_scalar($raw_redirect_to)) {
        $redirect_to = wp_validate_redirect((string) $raw_redirect_to, $default_redirect);
    }
}

$email_prefill    = '';
$email_candidate = '';

if (! empty($state_data['email'])) {
    $email_prefill = sanitize_text_field((string) $state_data['email']);
} else {
    if (get_query_var('email') !== '') {
        $email_candidate = get_query_var('email');
    } elseif (isset($_GET['email'])) {
        $email_candidate = wp_unslash($_GET['email']);
    }

    if ($email_candidate !== '') {
        if (! is_scalar($email_candidate)) {
            $email_candidate = '';
        } else {
            $email_candidate = (string) $email_candidate;
        }

        if ($email_candidate !== '') {
            $email_prefill = sanitize_email($email_candidate);

            if ($email_prefill === '') {
                $email_prefill = sanitize_text_field($email_candidate);
            }
        }
    }
}

$remember_param = get_query_var('remember');
if ($remember_param === '') {
    $remember_param = isset($_GET['remember']) ? wp_unslash($_GET['remember']) : '';
}
$remember_param = is_scalar($remember_param) ? (string) $remember_param : '';
$remember_checked = ! empty($state_data) ? ! empty($state_data['remember']) : in_array(sanitize_text_field($remember_param), ['1', 'on'], true);

$error_code = '';
$source_error = '';

if (! empty($state_data['error'])) {
    $error_code   = sanitize_key((string) $state_data['error']);
    $source_error = isset($state_data['source_error']) ? sanitize_key((string) $state_data['source_error']) : '';
} else {
    if (get_query_var('error') !== '') {
        $error_candidate = get_query_var('error');
    } elseif (isset($_GET['error'])) {
        $error_candidate = wp_unslash($_GET['error']);
    } else {
        $error_candidate = '';
    }

    if ($error_candidate !== '' && is_scalar($error_candidate)) {
        $error_code = sanitize_key((string) $error_candidate);
    }
}

$global_error_message   = '';
$email_error_message    = '';
$password_error_message = '';
$success_message        = '';

$notice_code = '';
if (! empty($state_data['notice'])) {
    $notice_code = sanitize_key((string) $state_data['notice']);
} else {
    $notice_candidate = get_query_var('reset');
    if ($notice_candidate === '') {
        $notice_candidate = isset($_GET['reset']) ? wp_unslash($_GET['reset']) : '';
    }
    if ($notice_candidate !== '' && is_scalar($notice_candidate)) {
        $notice_code = sanitize_key((string) $notice_candidate);
    }
}

switch ($error_code) {
    case 'email':
        $email_error_message = __('No encontramos ninguna cuenta con ese correo electrónico.', 'garantias-online-360vo');
        break;
    case 'password':
        $password_error_message = __('Contraseña incorrecta', 'garantias-online-360vo');
        break;
    case 'missing':
        if ($source_error === 'empty_username') {
            $email_error_message = __('Introduce tu correo electrónico.', 'garantias-online-360vo');
        } elseif ($source_error === 'empty_password') {
            $password_error_message = __('Introduce tu contraseña.', 'garantias-online-360vo');
        } else {
            $email_error_message    = __('Introduce tu correo electrónico.', 'garantias-online-360vo');
            $password_error_message = __('Introduce tu contraseña.', 'garantias-online-360vo');
        }
        break;
    case 'generic':
        $global_error_message = __('No hemos podido iniciar sesión. Inténtalo de nuevo.', 'garantias-online-360vo');
        break;
}

if ($notice_code === 'password_reset' || $notice_code === '1') {
    $success_message = __('Tu contraseña se ha actualizado correctamente. Inicia sesión con tus nuevas credenciales.', 'garantias-online-360vo');
}

$email_error_id    = $email_error_message !== '' ? 'user_login-error' : '';
$password_error_id = $password_error_message !== '' ? 'user_pass-error' : '';
$email_container_class = 'input-container' . ($email_error_message !== '' ? ' is-error' : '');
$password_container_class = 'input-container' . ($password_error_message !== '' ? ' is-error' : '');
?>

<main class="register-page login-page" style="view-transition-name: login">
    <?php TemplateLoader::load_part('auth-styles'); ?>

    <div class="container">
        <div class="login-card" style="view-transition-name: header">
            <span class="login-card__badge">
                <?php echo Svg::icon('shield', 'login-card__badge-icon'); ?>
                <span class="login-card__badge-text"><?php esc_html_e('Garantías Online', 'garantias-online-360vo'); ?></span>
                <span class="login-card__badge-status" aria-hidden="true"></span>
            </span>

            <div class="login-card__brand">
                <div class="login-card__logo" style="view-transition-name: logo">
                    <?php TemplateLoader::load_part('logo-inline'); ?>
                    <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
                </div>

                <div class="login-card__header">
                    <h2 class="login-card__title"><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h2>
                    <p class="login-card__subtitle"><?php esc_html_e('Introduce tu correo electrónico y tu contraseña para acceder al panel.', 'garantias-online-360vo'); ?></p>
                </div>
            </div>

            <?php if ($success_message !== '') : ?>
                <div class="form-alert form-alert--success" role="status">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($global_error_message !== '') : ?>
                <div class="form-alert" role="alert">
                    <?php echo esc_html($global_error_message); ?>
                </div>
            <?php endif; ?>

            <form
                name="loginform"
                id="loginform"
                action="<?php echo esc_url(home_url('/garantias-online/login/')); ?>"
                method="post"
                class="auth-form">
                <?php wp_nonce_field('go_login_action', 'go_login_nonce'); ?>
                <input type="hidden" name="go_auth_action" value="login">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <div class="form-row">
                    <div class="<?php echo esc_attr($email_container_class); ?>">
                        <input
                            name="log"
                            id="user_login"
                            type="email"
                            required
                            autocomplete="username"
                            inputmode="email"
                            autocapitalize="none"
                            value="<?php echo esc_attr($email_prefill); ?>"
                            <?php echo $email_error_message !== '' ? ' aria-invalid="true"' : ''; ?>
                            <?php echo $email_error_id !== '' ? ' aria-describedby="' . esc_attr($email_error_id) . '"' : ''; ?>
                            class="form-input"
                            placeholder=" ">
                        <label for="user_login" class="form-label">
                            <?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?>
                        </label>
                        <?php if ($email_error_message !== '') : ?>
                            <p class="form-field-error" id="<?php echo esc_attr($email_error_id); ?>">
                                <?php echo esc_html($email_error_message); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="<?php echo esc_attr($password_container_class); ?>">
                        <input
                            name="pwd"
                            id="user_pass"
                            type="password"
                            required
                            autocomplete="current-password"
                            <?php echo $password_error_message !== '' ? ' aria-invalid="true"' : ''; ?>
                            <?php echo $password_error_id !== '' ? ' aria-describedby="' . esc_attr($password_error_id) . '"' : ''; ?>
                            class="form-input"
                            placeholder=" ">
                        <label for="user_pass" class="form-label">
                            <?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?>
                        </label>
                        <button
                            type="button"
                            class="toggle-password"
                            data-toggle-target="user_pass"
                            aria-pressed="false">
                            <span class="screen-reader-text"><?php esc_html_e('Mostrar contraseña', 'garantias-online-360vo'); ?></span>
                            <?php echo Svg::icon('visibility', 'toggle-password__icon toggle-password__icon--on'); ?>
                            <?php echo Svg::icon('visibility_off', 'toggle-password__icon toggle-password__icon--off'); ?>
                        </button>
                        <?php if ($password_error_message !== '') : ?>
                            <p class="form-field-error" id="<?php echo esc_attr($password_error_id); ?>">
                                <?php echo esc_html($password_error_message); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="auth-form__meta">
                    <label class="remember-me" for="rememberme">
                        <input
                            name="rememberme"
                            id="rememberme"
                            type="checkbox"
                            value="forever"
                            <?php checked($remember_checked); ?>>
                        <span><?php esc_html_e('Recordarme', 'garantias-online-360vo'); ?></span>
                    </label>
                    <a class="form-link" href="<?php echo esc_url(home_url('/garantias-online/restablecer-clave/')); ?>">
                        <?php esc_html_e('He olvidado mi contraseña', 'garantias-online-360vo'); ?>
                    </a>
                </div>

                <div class="auth-form__footer">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn__icon" aria-hidden="true">
                            <?php echo Svg::icon('login'); ?>
                        </span>
                        <span><?php esc_html_e('Entrar', 'garantias-online-360vo'); ?></span>
                    </button>
                    <p class="info-cta">
                        <?php esc_html_e('¿No tienes cuenta?', 'garantias-online-360vo'); ?>
                        <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                            <?php esc_html_e('Regístrate', 'garantias-online-360vo'); ?>
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    (function () {
        const toggles = document.querySelectorAll('.toggle-password');
        toggles.forEach((toggle) => {
            const targetId = toggle.getAttribute('data-toggle-target');
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }

            toggle.addEventListener('click', () => {
                const isVisible = input.getAttribute('type') === 'text';
                input.setAttribute('type', isVisible ? 'password' : 'text');
                toggle.setAttribute('aria-pressed', String(!isVisible));
                const label = toggle.querySelector('.screen-reader-text');
                if (label) {
                    label.textContent = isVisible
                        ? <?php echo wp_json_encode(esc_html__('Mostrar contraseña', 'garantias-online-360vo')); ?>
                        : <?php echo wp_json_encode(esc_html__('Ocultar contraseña', 'garantias-online-360vo')); ?>;
                }
            });
        });
    })();
</script>

<?php
if ($is_login_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}
?>
