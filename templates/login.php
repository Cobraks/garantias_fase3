<?php
if (! defined('ABSPATH')) exit;



?>
<div class="auth-wrapper">
    <aside class="auth-side" style="view-transition-name: black-message">
        <div class="auth-info">
            <h2><?php esc_html_e('Gestiona tus garantías de forma sencilla', 'garantias-online-360vo'); ?></h2>
            <p><?php esc_html_e('Accede a tu panel de control para crear y consultar tus garantías.', 'garantias-online-360vo'); ?></p>
        </div>
    </aside>

    <section class="auth-page" style="view-transition-name: white-message">
        <h1><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h1>

        <?php if (isset($_GET['login']) && $_GET['login'] === 'failed'): ?>
            <div class="auth-error">
                <?php esc_html_e('Usuario o contraseña incorrectos', 'garantias-online-360vo'); ?>
            </div>
        <?php endif; ?>

        <form name="loginform" id="loginform"
            action="<?php echo esc_url(wp_login_url(home_url('/garantias-online/'))); ?>"
            method="post"
            class="auth-form">
            <div class="auth-form-group">
                <label for="user_login">
                    <?php esc_html_e('Usuario o correo', 'garantias-online-360vo'); ?>
                </label>
                <input name="log" id="user_login" type="text" required autocomplete="username">
            </div>

            <div class="auth-form-group">
                <label for="user_pass">
                    <?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?>
                </label>
                <input name="pwd" id="user_pass" type="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="button-primary">
                <?php esc_html_e('Entrar', 'garantias-online-360vo'); ?>
            </button>
            <input type="hidden" name="redirect_to"
                value="<?php echo esc_url(home_url('/garantias-online/')); ?>">
        </form>

        <p class="auth-switch">
            <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                <?php esc_html_e('¿No tienes cuenta? Regístrate', 'garantias-online-360vo'); ?>
            </a>
        </p>
    </section>
</div>
