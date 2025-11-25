<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_auth_page    = true;
$is_verify_page  = true;
$is_login_page   = false;
$is_register_page = false;

$email_prefill = '';
$email_param   = get_query_var('email');
if ($email_param === '') {
    $email_param = isset($_GET['email']) ? wp_unslash($_GET['email']) : '';
}
if (is_scalar($email_param)) {
    $email_prefill = sanitize_email((string) $email_param);
    if ($email_prefill === '') {
        $email_prefill = sanitize_text_field((string) $email_param);
    }
}

$verify_config = [
    'rest'           => [
        'root' => esc_url_raw(rest_url('go/public/v1/')),
    ],
    'prefill_email'  => $email_prefill,
];

TemplateLoader::load_part('header', compact('is_auth_page', 'is_verify_page', 'is_login_page', 'is_register_page'));
?>

<script>
    window.__GO_VERIFY__ = <?php echo wp_json_encode($verify_config, JSON_UNESCAPED_SLASHES); ?>;
</script>

<main class="register-page login-page verify-page">
    <?php TemplateLoader::load_part('auth-styles'); ?>

    <div class="container">
        <div class="login-card__logo login-card__logo--mobile">
            <?php TemplateLoader::load_part('logo-inline'); ?>
            <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
        </div>

        <div class="login-card" style="view-transition-name: verify-header;">
            <span class="login-card__badge">
                <?php echo Svg::icon('shield', 'login-card__badge-icon'); ?>
                <span class="login-card__badge-text"><?php esc_html_e('Garantías Online', 'garantias-online-360vo'); ?></span>
                <span class="login-card__badge-status" aria-hidden="true"></span>
            </span>
            <div class="login-card__layout">
                <div class="login-card__intro">
                    <div class="login-card__brand">
                        <div class="login-card__logo login-card__logo--desktop">
                            <?php TemplateLoader::load_part('logo-inline'); ?>
                            <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
                        </div>

                        <div class="login-card__header">
                            <h2 class="login-card__title"><?php esc_html_e('Verificar cuenta', 'garantias-online-360vo'); ?></h2>
                            <p class="login-card__subtitle">
                                <?php
                                echo wp_kses(
                                    __('Introduce el correo que usaste al registrarte para recuperar tu código y activarlo.', 'garantias-online-360vo'),
                                    []
                                );
                                ?>
                            </p>
                        </div>
                    </div>

                    <p class="info-cta info-cta--login info-cta--login-desktop">
                        <?php esc_html_e('¿Ya tienes la cuenta verificada?', 'garantias-online-360vo'); ?>
                        <a href="<?php echo esc_url(home_url('/garantias-online/login/')); ?>">
                            <?php esc_html_e('Inicia sesión', 'garantias-online-360vo'); ?>
                        </a>
                    </p>
                </div>

                <div class="login-card__body">
                    <div class="form-alert" id="verification-global" role="status" hidden></div>

                    <form class="auth-form" id="verification-form" novalidate>
                        <div class="form-row">
                            <div class="input-container" id="verification-email-container">
                                <input
                                    name="verification_email"
                                    id="verification_email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    inputmode="email"
                                    autocapitalize="none"
                                    value="<?php echo esc_attr($email_prefill); ?>"
                                    class="form-input"
                                    placeholder=" "
                                >
                                <label for="verification_email" class="form-label">
                                    <?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?>
                                </label>
                                <p class="form-field-error" id="verification-email-error" hidden></p>
                            </div>
                        </div>

                        <div class="auth-form__footer auth-form__footer--stacked">
                            <button type="button" class="btn btn-primary" id="lookup-btn">
                                <span class="btn__icon" aria-hidden="true">
                                    <?php echo Svg::icon('mail'); ?>
                                </span>
                                <span><?php esc_html_e('Continuar', 'garantias-online-360vo'); ?></span>
                            </button>
                            <a class="form-link" href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                                <?php esc_html_e('Volver al registro', 'garantias-online-360vo'); ?>
                            </a>
                        </div>

                        <div class="verification-container" id="verification-block" hidden>
                            <div class="verification-icon">✓</div>
                            <h3 class="verification-title"><?php esc_html_e('Introduce o solicita tu código', 'garantias-online-360vo'); ?></h3>
                            <p class="verification-text" id="verification-instructions">
                                <?php
                                printf(
                                    wp_kses(
                                        /* translators: %s: email address */
                                        __('Te hemos enviado un código de verificación a <strong id="verification-email-target">%s</strong>. Introduce el código o solicita uno nuevo.', 'garantias-online-360vo'),
                                        ['strong' => ['id' => []]]
                                    ),
                                    esc_html($email_prefill !== '' ? $email_prefill : '—')
                                );
                                ?>
                                <span id="verification-expiry" class="verification-hint" hidden></span>
                            </p>
                            <p class="verification-message" id="verification-message" role="status" aria-live="assertive" hidden></p>

                            <div class="input-container input-container--verification">
                                <input type="text" id="verification_code" class="form-input" placeholder=" " inputmode="numeric" autocomplete="one-time-code" disabled required>
                                <label for="verification_code" class="form-label"><?php esc_html_e('Código de verificación', 'garantias-online-360vo'); ?></label>
                                <p class="form-field-error" id="verification-code-error" hidden></p>
                            </div>

                            <div class="verification-actions">
                                <button type="button" class="btn btn-secondary" id="resend-code-btn">
                                    <span class="btn__icon" aria-hidden="true"><?php echo Svg::icon('refresh'); ?></span>
                                    <span><?php esc_html_e('Solicitar nuevo código', 'garantias-online-360vo'); ?></span>
                                </button>
                                <button type="button" class="btn btn-primary" id="verify-btn">
                                    <span class="btn__icon" aria-hidden="true"><?php echo Svg::icon('done'); ?></span>
                                    <span><?php esc_html_e('Introducir el código', 'garantias-online-360vo'); ?></span>
                                </button>
                            </div>
                            <p class="verification-hint" id="resend-countdown" hidden></p>

                            <div class="verification-success" id="verification-success" hidden>
                                <p id="verification-success-message"><?php esc_html_e('Cuenta verificada. Ya puedes acceder a tu área de usuario.', 'garantias-online-360vo'); ?></p>
                                <a href="<?php echo esc_url(home_url('/garantias-online/')); ?>" class="btn btn-primary verification-success__cta"><?php esc_html_e('Entrar a Mis garantías', 'garantias-online-360vo'); ?></a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?php echo esc_url(plugins_url('assets/js/verify.min.js', GARANTIAS360VO__FILE__)); ?>" defer></script>

<?php TemplateLoader::load_part('footer', compact('is_auth_page', 'is_verify_page')); ?>
