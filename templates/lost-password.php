<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Auth\AuthController;
use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_embedded          = ! empty($is_embedded);
$is_recovery_page     = ! $is_embedded;
$is_auth_page         = true;
$is_login_page        = false;
$is_register_page     = false;

if ($is_recovery_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page', 'is_register_page', 'is_recovery_page'));
}

$default_login_url = home_url('/garantias-online/login/');
$redirect_to       = $default_login_url;
$raw_redirect      = '';
$include_redirect_field = false;

$state_token = get_query_var('state');
if ($state_token === '') {
    $state_token = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
}
$state_token = is_scalar($state_token) ? (string) $state_token : '';

$state_data = $state_token !== '' ? AuthController::consume_lost_password_state($state_token) : [];

if (! empty($state_data['redirect_to'])) {
    $redirect_to = $state_data['redirect_to'];
    if ($redirect_to !== $default_login_url) {
        $raw_redirect = $redirect_to;
        $include_redirect_field = true;
    }
} else {
    $raw_redirect_candidate = get_query_var('redirect_to');
    if ($raw_redirect_candidate === '') {
        $raw_redirect_candidate = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : '';
    }
    if ($raw_redirect_candidate !== '' && is_scalar($raw_redirect_candidate)) {
        $validated_redirect = wp_validate_redirect((string) $raw_redirect_candidate, $default_login_url);
        $redirect_to = $validated_redirect;
        if ($validated_redirect !== $default_login_url) {
            $raw_redirect = $validated_redirect;
            $include_redirect_field = true;
        }
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

$error_code   = '';
$source_error = '';

if (! empty($state_data)) {
    if (! empty($state_data['error'])) {
        $error_code   = sanitize_key((string) $state_data['error']);
        $source_error = isset($state_data['source_error']) ? sanitize_key((string) $state_data['source_error']) : '';
    }
    $sent_status = ! empty($state_data['sent']);
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

    $sent_param = get_query_var('sent');
    if ($sent_param === '') {
        $sent_param = isset($_GET['sent']) ? wp_unslash($_GET['sent']) : '';
    }
    $sent_param  = is_scalar($sent_param) ? (string) $sent_param : '';
    $sent_status = sanitize_key($sent_param) === '1';
}

$notice_message = '';
$notice_class   = 'form-alert';
$notice_role    = 'alert';

if ($sent_status) {
    $notice_message = __('Si tu correo está registrado, recibirás un email con las instrucciones para restablecer tu contraseña en los próximos minutos.', 'garantias-online-360vo');
    $notice_class  .= ' form-alert--success';
    $notice_role    = 'status';
} else {
    switch ($error_code) {
        case 'email':
            $notice_message = __('No encontramos ninguna cuenta con ese correo electrónico. Comprueba que esté bien escrito.', 'garantias-online-360vo');
            break;
        case 'generic':
            $notice_message = __('No hemos podido enviar el correo de restablecimiento. Inténtalo de nuevo en unos instantes.', 'garantias-online-360vo');
            break;
    }
}

$email_error_message = '';
$email_error_id      = '';

if ($error_code === 'email') {
    $email_error_message = __('No encontramos ninguna cuenta con ese correo electrónico.', 'garantias-online-360vo');
} elseif ($error_code === 'generic' && $source_error !== '') {
    $email_error_message = __('No hemos podido procesar tu solicitud. Inténtalo de nuevo.', 'garantias-online-360vo');
}

if ($email_error_message !== '') {
    $email_error_id = 'lost-email-error';
}

$email_container_class = 'input-container' . ($email_error_message !== '' ? ' is-error' : '');

$login_link = $default_login_url;
if ($redirect_to !== $default_login_url) {
    $login_link = add_query_arg('redirect_to', $redirect_to, $login_link);
}
?>

<main class="register-page login-page" style="view-transition-name: login">
    <?php TemplateLoader::load_part('auth-styles'); ?>

    <div class="container">
        <div class="login-card__logo login-card__logo--mobile">
            <?php TemplateLoader::load_part('logo-inline'); ?>
            <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
        </div>

        <div class="login-card" style="view-transition-name: header">
            <span class="login-card__badge">
                <?php echo Svg::icon('shield', 'login-card__badge-icon'); ?>
                <span class="login-card__badge-text"><?php esc_html_e('Garantías Online', 'garantias-online-360vo'); ?></span>
                <span class="login-card__badge-status" aria-hidden="true"></span>
            </span>

            <div class="login-card__layout">
                <div class="login-card__intro">
                    <div class="login-card__brand">
                        <div class="login-card__logo login-card__logo--desktop" style="view-transition-name: logo">
                            <?php TemplateLoader::load_part('logo-inline'); ?>
                            <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
                        </div>

                        <div class="login-card__header">
                            <h2 class="login-card__title"><?php esc_html_e('Restablecer contraseña', 'garantias-online-360vo'); ?></h2>
                            <p class="login-card__subtitle"><?php esc_html_e('Introduce tu dirección de correo electrónico y te enviaremos las instrucciones para restablecer tu contraseña.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="login-card__body">
                    <?php if ($notice_message !== '') : ?>
                        <div class="<?php echo esc_attr($notice_class); ?>" role="<?php echo esc_attr($notice_role); ?>">
                            <?php echo esc_html($notice_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (! $sent_status) : ?>
                        <form
                            action="<?php echo esc_url(home_url('/garantias-online/restablecer-clave/')); ?>"
                            method="post"
                            class="auth-form auth-form--lost">
                            <?php wp_nonce_field('go_lost_password_action', 'go_lost_nonce'); ?>
                            <input type="hidden" name="go_auth_action" value="lostpassword">
                            <?php if ($include_redirect_field) : ?>
                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                            <?php endif; ?>

                            <div class="form-row">
                                <div class="<?php echo esc_attr($email_container_class); ?>">
                                    <input
                                        type="email"
                                        name="user_login"
                                        id="user_login"
                                        required
                                        autocomplete="email"
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

                            <div class="auth-form__footer">
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn__icon" aria-hidden="true">
                                        <?php echo Svg::icon('email'); ?>
                                    </span>
                                    <span><?php esc_html_e('Obtener contraseña nueva', 'garantias-online-360vo'); ?></span>
                                </button>
                                <p class="info-cta info-cta--lost">
                                    <?php esc_html_e('¿Ya recuerdas tu contraseña?', 'garantias-online-360vo'); ?>
                                    <a href="<?php echo esc_url($login_link); ?>">
                                        <?php esc_html_e('Volver a iniciar sesión', 'garantias-online-360vo'); ?>
                                    </a>
                                </p>
                            </div>
                        </form>
                    <?php else : ?>
                        <p class="info-cta info-cta--lost">
                            <?php esc_html_e('¿Ya recuerdas tu contraseña?', 'garantias-online-360vo'); ?>
                            <a href="<?php echo esc_url($login_link); ?>">
                                <?php esc_html_e('Volver a iniciar sesión', 'garantias-online-360vo'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
if ($is_recovery_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}
?>
