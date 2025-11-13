<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Auth\AuthController;
use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_embedded      = ! empty($is_embedded);
$is_reset_page    = ! $is_embedded;
$is_auth_page     = true;
$is_login_page    = false;
$is_register_page = false;

if ($is_reset_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page', 'is_register_page'));
}

$default_login_url = home_url('/garantias-online/login/');
$lost_password_url = home_url('/garantias-online/restablecer-clave/');

$state_token = get_query_var('state');
if ($state_token === '') {
    $state_token = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
}
$state_token = is_scalar($state_token) ? (string) $state_token : '';

$state_data = $state_token !== '' ? AuthController::consume_reset_password_state($state_token) : [];

$login_identifier = '';
$key_param        = '';
$global_error     = '';
$pass1_error      = '';
$pass2_error      = '';
$status           = '';

if (! empty($state_data)) {
    $login_identifier = isset($state_data['login']) ? (string) $state_data['login'] : '';
    $key_param        = isset($state_data['key']) ? (string) $state_data['key'] : '';
    $global_error     = isset($state_data['global_error']) ? (string) $state_data['global_error'] : '';
    $pass1_error      = isset($state_data['pass1_error']) ? (string) $state_data['pass1_error'] : '';
    $pass2_error      = isset($state_data['pass2_error']) ? (string) $state_data['pass2_error'] : '';
    $status           = isset($state_data['status']) ? sanitize_key((string) $state_data['status']) : '';
}

if ($login_identifier === '') {
    $login_candidate = get_query_var('login');
    if ($login_candidate === '') {
        $login_candidate = isset($_GET['login']) ? wp_unslash($_GET['login']) : '';
    }
    if ($login_candidate !== '' && is_scalar($login_candidate)) {
        $login_identifier = (string) $login_candidate;
    }
}

if ($key_param === '') {
    $key_candidate = get_query_var('key');
    if ($key_candidate === '') {
        $key_candidate = isset($_GET['key']) ? wp_unslash($_GET['key']) : '';
    }
    if ($key_candidate !== '' && is_scalar($key_candidate)) {
        $key_param = (string) $key_candidate;
    }
}

$login_identifier = sanitize_text_field($login_identifier);
$key_param        = sanitize_text_field($key_param);

$token_valid = true;
if ($status === 'invalid') {
    $token_valid = false;
} else {
    $validation = AuthController::validate_reset_key($key_param, $login_identifier);
    if ($validation instanceof \WP_Error) {
        $token_valid = false;
        if ($global_error === '') {
            $global_error = AuthController::describe_reset_key_error($validation);
        }
    }
}

$show_form = $token_valid;

$pass1_error_id = $pass1_error !== '' ? 'new-password-error' : '';
$pass2_error_id = $pass2_error !== '' ? 'confirm-password-error' : '';
$pass1_container_class = 'input-container' . ($pass1_error !== '' ? ' is-error' : '');
$pass2_container_class = 'input-container' . ($pass2_error !== '' ? ' is-error' : '');

$subtitle_message = $show_form
    ? __('Introduce tu nueva contraseña y confírmala para recuperar el acceso.', 'garantias-online-360vo')
    : __('Este enlace ya no está disponible. Solicita un nuevo correo de restablecimiento.', 'garantias-online-360vo');

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
                <h2 class="login-card__title"><?php esc_html_e('Crear nueva contraseña', 'garantias-online-360vo'); ?></h2>
                <p class="login-card__subtitle"><?php echo esc_html($subtitle_message); ?></p>
            </div>

            <?php if ($global_error !== '') : ?>
                <div class="form-alert" role="alert">
                    <?php echo esc_html($global_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form) : ?>
                <form
                    action="<?php echo esc_url(AuthController::get_reset_password_action_url($login_identifier, $key_param)); ?>"
                    method="post"
                    class="auth-form">
                    <?php wp_nonce_field('go_reset_password_action', 'go_reset_nonce'); ?>
                    <input type="hidden" name="go_auth_action" value="resetpassword">
                    <input type="hidden" name="user_login" value="<?php echo esc_attr($login_identifier); ?>">
                    <input type="hidden" name="rp_key" value="<?php echo esc_attr($key_param); ?>">

                    <div class="form-row">
                        <div class="<?php echo esc_attr($pass1_container_class); ?>">
                            <input
                                type="password"
                                name="pass1"
                                id="pass1"
                                required
                                autocomplete="new-password"
                                class="form-input"
                                <?php echo $pass1_error !== '' ? ' aria-invalid="true"' : ''; ?>
                                <?php echo $pass1_error_id !== '' ? ' aria-describedby="' . esc_attr($pass1_error_id) . '"' : ''; ?>
                                placeholder=" ">
                            <label for="pass1" class="form-label">
                                <?php esc_html_e('Nueva contraseña', 'garantias-online-360vo'); ?>
                            </label>
                            <button
                                type="button"
                                class="toggle-password"
                                data-toggle-target="pass1"
                                aria-pressed="false">
                                <span class="screen-reader-text"><?php esc_html_e('Mostrar contraseña', 'garantias-online-360vo'); ?></span>
                                <?php echo Svg::icon('visibility', 'toggle-password__icon toggle-password__icon--on'); ?>
                                <?php echo Svg::icon('visibility_off', 'toggle-password__icon toggle-password__icon--off'); ?>
                            </button>
                            <?php if ($pass1_error !== '') : ?>
                                <p class="form-field-error" id="<?php echo esc_attr($pass1_error_id); ?>">
                                    <?php echo esc_html($pass1_error); ?>
                                </p>
                            <?php endif; ?>
                            <p class="form-hint"><?php esc_html_e('Mínimo 8 caracteres con números y símbolos.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="<?php echo esc_attr($pass2_container_class); ?>">
                            <input
                                type="password"
                                name="pass2"
                                id="pass2"
                                required
                                autocomplete="new-password"
                                class="form-input"
                                <?php echo $pass2_error !== '' ? ' aria-invalid="true"' : ''; ?>
                                <?php echo $pass2_error_id !== '' ? ' aria-describedby="' . esc_attr($pass2_error_id) . '"' : ''; ?>
                                placeholder=" ">
                            <label for="pass2" class="form-label">
                                <?php esc_html_e('Confirmar contraseña', 'garantias-online-360vo'); ?>
                            </label>
                            <button
                                type="button"
                                class="toggle-password"
                                data-toggle-target="pass2"
                                aria-pressed="false">
                                <span class="screen-reader-text"><?php esc_html_e('Mostrar contraseña', 'garantias-online-360vo'); ?></span>
                                <?php echo Svg::icon('visibility', 'toggle-password__icon toggle-password__icon--on'); ?>
                                <?php echo Svg::icon('visibility_off', 'toggle-password__icon toggle-password__icon--off'); ?>
                            </button>
                            <?php if ($pass2_error !== '') : ?>
                                <p class="form-field-error" id="<?php echo esc_attr($pass2_error_id); ?>">
                                    <?php echo esc_html($pass2_error); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="auth-form__footer">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn__icon" aria-hidden="true">
                                <?php echo Svg::icon('check'); ?>
                            </span>
                            <span><?php esc_html_e('Guardar nueva contraseña', 'garantias-online-360vo'); ?></span>
                        </button>
                        <p class="info-cta">
                            <?php esc_html_e('¿Recuerdas tu contraseña?', 'garantias-online-360vo'); ?>
                            <a href="<?php echo esc_url($default_login_url); ?>"><?php esc_html_e('Volver a iniciar sesión', 'garantias-online-360vo'); ?></a>
                        </p>
                    </div>
                </form>
            <?php else : ?>
                <div class="auth-form__footer">
                    <p class="info-cta">
                        <?php esc_html_e('¿Necesitas un nuevo enlace?', 'garantias-online-360vo'); ?>
                        <a href="<?php echo esc_url($lost_password_url); ?>"><?php esc_html_e('Solicitar restablecimiento de contraseña', 'garantias-online-360vo'); ?></a>
                    </p>
                    <p class="info-cta">
                        <?php esc_html_e('¿Quieres volver al inicio de sesión?', 'garantias-online-360vo'); ?>
                        <a href="<?php echo esc_url($default_login_url); ?>"><?php esc_html_e('Ir a iniciar sesión', 'garantias-online-360vo'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
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
if ($is_reset_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}
?>
