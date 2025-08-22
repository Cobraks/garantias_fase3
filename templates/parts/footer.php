<?php if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
</div> <!-- /.main-grid -->
</div> <!-- /.container -->
<footer class="footer" style="view-transition-name: footer">
    <div class="footer__wrapper">
        <p class="footer__text"><?php echo '©'  . esc_html(date('Y')) . ' ' . '<span class="text--red">360</span>VO '; ?></p>
        <?php if (! empty($is_add_guarantee)) : ?>
            <button id="rellenar_ejemplo" class="btn"><?php esc_html_e('Rellenar datos ejemplo', 'garantias-online-360vo'); ?></button>
        <?php endif; ?>
        <nav class="footer__legal">
            <ul>
                <li><a href="#"><?php esc_html_e('Aviso Legal', 'garantias-online-360vo'); ?></a></li>
                <li><a href="#"><?php esc_html_e('Política de Privacidad', 'garantias-online-360vo'); ?></a></li>
                <li><a href="#"><?php esc_html_e('Términos y Condiciones', 'garantias-online-360vo'); ?></a></li>
            </ul>
        </nav>
    </div>
</footer>


<?php if ($is_dashboard_page ?? false) : ?>
    <script
        src="<?php echo esc_url(plugins_url('assets/js/dashboard.min.js', GARANTIAS360VO__FILE__)); ?>"
        defer></script>
<?php endif; ?>

<?php if ($is_list_page ?? false) : ?>
    <?php
    if (!isset($current_user) || !($current_user instanceof WP_User)) {
        $current_user = wp_get_current_user();
    }
    $is_admin = user_can($current_user, 'manage_options');
    $is_comercial = in_array('go_comercial', (array) $current_user->roles, true);
    if ($is_admin) {
        $js_user_role = 'admin';
    } elseif (in_array('go_profesional', (array) $current_user->roles, true)) {
        $js_user_role = 'go_profesional';
    } elseif (in_array('go_garantias', (array) $current_user->roles, true)) {
        $js_user_role = 'go_garantias';
    } elseif ($is_comercial) {
        $js_user_role = 'go_comercial';
    } else {
        $js_user_role = 'user';
    }
    ?>
    <script>
        window.__GO_CONFIG__ = {
            rest: {
                root: "<?php echo esc_url(rest_url()); ?>",
                nonce: "<?php echo esc_js(wp_create_nonce('wp_rest')); ?>"
            },
            user: {
                role: "<?php echo esc_js($js_user_role); ?>"
            }
        };
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/mis_garantias.min.js', GARANTIAS360VO__FILE__)); ?>" defer></script>
<?php endif; ?>

<?php if (($is_add_guarantee ?? false)) : ?>
    <?php
    // Asegura que las variables existen (y previene errores)
    if (!isset($current_user) || !($current_user instanceof WP_User)) {
        $current_user = wp_get_current_user();
    }
    $is_admin = $is_admin ?? user_can($current_user, 'manage_options');
    $is_comercial = $is_comercial ?? in_array('go_comercial', (array)$current_user->roles, true);
    $icon_percent_html = Svg::icon('percent');
    $icon_warning_html = Svg::icon('warning');
    $icon_check_html = Svg::icon('check');
    $icon_pdf_html = Svg::icon('pdf');
    $icon_save_html = Svg::icon('save');

    if ($is_admin) {
        $js_user_role = 'admin';
    } elseif (in_array('go_profesional', (array)$current_user->roles, true)) {
        $js_user_role = 'go_profesional';
    } elseif ($is_comercial) {
        $js_user_role = 'comercial';
    } else {
        $js_user_role = 'user';
    }
    ?>
    <script>
        window.__GO_CONFIG__ = {
            rest: {
                root: "<?php echo esc_url(rest_url()); ?>",
                nonce: "<?php echo esc_js(wp_create_nonce('wp_rest')); ?>"
            },
            user: {
                role: "<?php
                        $is_admin = $is_admin ?? user_can($current_user, 'manage_options');
                        $is_comercial = $is_comercial ?? in_array('go_comercial', (array)$current_user->roles, true);
                        if ($is_admin) {
                            echo 'admin';
                        } elseif (in_array('go_profesional', (array)$current_user->roles, true)) {
                            echo 'go_profesional';
                        } elseif ($is_comercial) {
                            echo 'comercial';
                        } else {
                            echo 'user';
                        }
                        ?>",
                currentUserId: <?php echo (int) get_current_user_id(); ?>
            },
            icons: {
                percent: `<?php echo addslashes($icon_percent_html); ?>`,
                check: `<?php echo addslashes($icon_check_html); ?>`,
                warning: `<?php echo addslashes($icon_warning_html); ?>`,
                pdf: `<?php echo addslashes($icon_pdf_html); ?>`,
                save: `<?php echo addslashes($icon_save_html); ?>`
            }
        };
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/nueva_garantia.min.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>

<?php endif; ?>






</body>

</html>