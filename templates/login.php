<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_embedded   = ! empty($is_embedded);
$is_login_page = ! $is_embedded;
$is_auth_page  = true;

if ($is_login_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page'));
}

$default_redirect = home_url('/garantias-online/');
$raw_redirect_to  = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : '';
$redirect_to      = $raw_redirect_to !== ''
    ? wp_validate_redirect($raw_redirect_to, $default_redirect)
    : $default_redirect;

$email_prefill = '';
if (isset($_GET['email'])) {
    $email_candidate = wp_unslash($_GET['email']);
    $email_prefill   = sanitize_email($email_candidate);

    if ($email_prefill === '') {
        $email_prefill = sanitize_text_field($email_candidate);
    }
}

$remember_param   = isset($_GET['remember']) ? wp_unslash($_GET['remember']) : '';
$remember_checked = in_array(sanitize_text_field($remember_param), ['1', 'on'], true);

$error_code = '';
if (isset($_GET['error'])) {
    $error_code = sanitize_key(wp_unslash($_GET['error']));
}

$error_message = '';
switch ($error_code) {
    case 'email':
        $error_message = __('Correo electrónico incorrecto.', 'garantias-online-360vo');
        break;
    case 'password':
        $error_message = $email_prefill !== ''
            ? sprintf(__('Contraseña incorrecta para %s.', 'garantias-online-360vo'), $email_prefill)
            : __('Contraseña incorrecta.', 'garantias-online-360vo');
        break;
    case 'missing':
        $error_message = __('Introduce tu correo electrónico y tu contraseña.', 'garantias-online-360vo');
        break;
    case 'generic':
        $error_message = __('No hemos podido iniciar sesión. Inténtalo de nuevo.', 'garantias-online-360vo');
        break;
}
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
            <div class="login-card__logo" style="view-transition-name: logo">
                <?php TemplateLoader::load_part('logo-inline'); ?>
                <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
            </div>

            <div class="login-card__header">
                <h2 class="login-card__title"><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h2>
                <p class="login-card__subtitle"><?php esc_html_e('Introduce tu correo electrónico y tu contraseña para acceder al panel.', 'garantias-online-360vo'); ?></p>
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="form-alert" role="alert">
                    <?php echo esc_html($error_message); ?>
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
                    <div class="input-container">
                        <input
                            name="log"
                            id="user_login"
                            type="email"
                            required
                            autocomplete="username"
                            inputmode="email"
                            autocapitalize="none"
                            value="<?php echo esc_attr($email_prefill); ?>"
                            <?php echo in_array($error_code, ['email', 'missing'], true) ? ' aria-invalid="true"' : ''; ?>
                            class="form-input"
                            placeholder=" ">
                        <label for="user_login" class="form-label">
                            <?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <input
                            name="pwd"
                            id="user_pass"
                            type="password"
                            required
                            autocomplete="current-password"
                            <?php echo in_array($error_code, ['password', 'missing'], true) ? ' aria-invalid="true"' : ''; ?>
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
