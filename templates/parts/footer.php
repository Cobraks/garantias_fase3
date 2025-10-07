<?php if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Docs\ReclamationDocument;
use GarantiasOnline360VO\Support\FeatureFlags;
use GarantiasOnline360VO\Support\UserProfileResolver;

$example_data_enabled = false;
if (! empty($is_add_guarantee)) {
    $example_data_enabled = FeatureFlags::is_example_data_enabled();
}
?>
</div> <!-- /.main-grid -->
</div> <!-- /.container -->
<footer class="footer" style="view-transition-name: footer">
    <div class="footer__wrapper">
        <div class="footer__brand">
            <p class="footer__text"><?php echo '©'  . esc_html(date('Y')) . ' ' . '<span class="text--red">360</span>VO '; ?></p>
            <?php $show_footer_theme_toggle = ! is_user_logged_in(); ?>
            <?php if (! empty($is_auth_page)) {
                $show_footer_theme_toggle = true;
            } ?>
            <?php if ($show_footer_theme_toggle) : ?>
                <button
                    type="button"
                    class="footer__theme-toggle"
                    role="switch"
                    aria-checked="false"
                    data-theme-toggle
                    data-theme-label-off="<?php esc_attr_e('Activar modo oscuro', 'garantias-online-360vo'); ?>"
                    data-theme-label-on="<?php esc_attr_e('Activar modo claro', 'garantias-online-360vo'); ?>"
                    data-theme-status-off="<?php esc_attr_e('Desactivado', 'garantias-online-360vo'); ?>"
                    data-theme-status-on="<?php esc_attr_e('Activado', 'garantias-online-360vo'); ?>"
                    aria-label="<?php esc_attr_e('Activar modo oscuro', 'garantias-online-360vo'); ?>"
                >
                    <span class="footer__theme-icon" aria-hidden="true">
                        <?php echo Svg::icon('sun', 'footer__theme-icon-sun'); ?>
                        <?php echo Svg::icon('moon', 'footer__theme-icon-moon'); ?>
                    </span>
                    <span class="footer__theme-switch" aria-hidden="true">
                        <span class="footer__theme-thumb"></span>
                    </span>
                </button>
            <?php endif; ?>
        </div>
        <?php if (! empty($is_add_guarantee) && $example_data_enabled) : ?>
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
    $roles = (array) $current_user->roles;
    $is_admin = user_can($current_user, 'manage_options');
    $is_comercial = in_array('go_comercial', $roles, true);
    $is_director = in_array('go_director_comercial', $roles, true);
    if ($is_admin) {
        $js_user_role = 'admin';
    } elseif (in_array('go_profesional', $roles, true)) {
        $js_user_role = 'go_profesional';
    } elseif (in_array('go_garantias', $roles, true)) {
        $js_user_role = 'go_garantias';
    } elseif ($is_director) {
        $js_user_role = 'go_director_comercial';
    } elseif ($is_comercial) {
        $js_user_role = 'go_comercial';
    } else {
        $js_user_role = 'user';
    }
    $icon_pdf_html = Svg::icon('pdf');
    $icon_download_html = Svg::icon('download');
    $icon_plus_html = Svg::icon('plus');
    $icon_arrow_down_html = Svg::icon('arrow_drop_down');
    $icon_arrow_up_html = Svg::icon('arrow_drop_up');
    $reclamation_url = ReclamationDocument::get_url();
    ?>
    <script>
        window.__GO_CONFIG__ = {
            rest: {
                root: "<?php echo esc_url(rest_url()); ?>",
                nonce: "<?php echo esc_js(wp_create_nonce('wp_rest')); ?>"
            },
            user: {
                role: "<?php echo esc_js($js_user_role); ?>"
            },
            icons: {
                pdf: `<?php echo addslashes($icon_pdf_html); ?>`,
                plus: `<?php echo addslashes($icon_plus_html); ?>`,
                arrowDropDown: `<?php echo addslashes($icon_arrow_down_html); ?>`,
                arrowDropUp: `<?php echo addslashes($icon_arrow_up_html); ?>`,
                download: `<?php echo addslashes($icon_download_html); ?>`
            },
            pages: {
                misGarantias: "<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>",
                nuevaGarantia: "<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>",
                clientes: "<?php echo esc_url(trailingslashit(home_url('/garantias-online/clientes/'))); ?>"
            },
            documents: {
                reclamacion: "<?php echo esc_url($reclamation_url); ?>"
            },
            features: {
                exampleData: <?php echo $example_data_enabled ? 'true' : 'false'; ?>
            }
        };
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/mis_garantias.min.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>
<?php endif; ?>

<?php if ($is_clients_page ?? false) : ?>
    <?php
    if (!isset($current_user) || !($current_user instanceof WP_User)) {
        $current_user = wp_get_current_user();
    }

    $clients_config = [
        'rest' => [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ],
        'pagination' => [
            'perPage' => 20,
        ],
        'permissions' => [
            'canAssignCommercials' => current_user_can('manage_options'),
        ],
        'router' => [
            'basePath' => trailingslashit(wp_make_link_relative(home_url('/garantias-online/clientes/'))),
        ],
        'strings' => [
            'profile'              => __('Perfil', 'garantias-online-360vo'),
            'client'               => __('Cliente', 'garantias-online-360vo'),
            'noResults'            => __('No se han encontrado clientes con los filtros actuales.', 'garantias-online-360vo'),
            'offersEmpty'          => __('Sin ofertas activas', 'garantias-online-360vo'),
            'commercialsEmpty'     => __('Sin comercial asignado', 'garantias-online-360vo'),
            'detailTitle'          => __('Detalles del cliente', 'garantias-online-360vo'),
            'detailLoading'        => __('Cargando datos del cliente…', 'garantias-online-360vo'),
            'selectPrompt'         => __('Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.', 'garantias-online-360vo'),
            'error'                => __('No se ha podido cargar la información de clientes.', 'garantias-online-360vo'),
            'loginEmail'           => __('Email de inicio de sesión', 'garantias-online-360vo'),
            'notificationEmail'    => __('Email de notificaciones', 'garantias-online-360vo'),
            'notificationEmailSame'=> __('Igual que el email de inicio de sesión', 'garantias-online-360vo'),
            'notificationEmailDifferent' => __('El email de notificaciones es distinto al de acceso.', 'garantias-online-360vo'),
            'contactPhone'         => __('Teléfono de contacto', 'garantias-online-360vo'),
            'contactEmpty'         => __('No hay datos de contacto disponibles', 'garantias-online-360vo'),
            'contact'              => __('Contacto', 'garantias-online-360vo'),
            'company'              => __('Empresa', 'garantias-online-360vo'),
            'legalName'            => __('Razón social', 'garantias-online-360vo'),
            'taxId'                => __('CIF/NIF', 'garantias-online-360vo'),
            'address'              => __('Dirección', 'garantias-online-360vo'),
            'offers'               => __('Ofertas activas', 'garantias-online-360vo'),
            'commercials'          => __('Comercial', 'garantias-online-360vo'),
            'sepaStatus'           => __('Estado SEPA', 'garantias-online-360vo'),
            'sepaEmpty'            => __('Sin información del mandato', 'garantias-online-360vo'),
            'sepaDetails'          => __('Ver datos del deudor SEPA', 'garantias-online-360vo'),
            'paymentMethod'        => __('Método de pago', 'garantias-online-360vo'),
            'salesChannel'         => __('Canal de venta', 'garantias-online-360vo'),
            'registered'           => __('Registro', 'garantias-online-360vo'),
            'guarantees'           => __('Nº Garantías', 'garantias-online-360vo'),
            'channelFilterAll'     => __('Todos los canales', 'garantias-online-360vo'),
            'workshop'             => __('Taller propio', 'garantias-online-360vo'),
            'workshopYes'          => __('Con taller propio', 'garantias-online-360vo'),
            'workshopNo'           => __('Sin taller propio', 'garantias-online-360vo'),
            'workshopName'         => __('Nombre del taller', 'garantias-online-360vo'),
            'workshopContact'      => __('Persona de contacto', 'garantias-online-360vo'),
            'workshopPhone'        => __('Teléfono', 'garantias-online-360vo'),
            'workshopEmail'        => __('Email', 'garantias-online-360vo'),
            'workshopAddress'      => __('Dirección', 'garantias-online-360vo'),
            'workshopTaxId'        => __('CIF/NIF', 'garantias-online-360vo'),
            'workshopFiscal'       => __('Denominación fiscal', 'garantias-online-360vo'),
            'adminLink'            => __('Abrir ficha de administración del cliente', 'garantias-online-360vo'),
            'preferences'          => __('Configuración adicional', 'garantias-online-360vo'),
            'signatureTitle'       => __('Firma y sello en certificados', 'garantias-online-360vo'),
            'signatureEnabled'     => __('Incluye firma y sello en los certificados', 'garantias-online-360vo'),
            'signatureDisabled'    => __('No se añaden a los certificados', 'garantias-online-360vo'),
            'signatureUploaded'    => __('Firma subida', 'garantias-online-360vo'),
            'signatureMissing'     => __('Firma no disponible', 'garantias-online-360vo'),
            'sealUploaded'         => __('Sello subido', 'garantias-online-360vo'),
            'sealMissing'          => __('Sello no disponible', 'garantias-online-360vo'),
            'web360Title'          => __('Web 360VO', 'garantias-online-360vo'),
            'web360Enabled'        => __('Web 360VO activa', 'garantias-online-360vo'),
            'web360Disabled'       => __('Sin web configurada', 'garantias-online-360vo'),
            'web360Link'           => __('Abrir sitio', 'garantias-online-360vo'),
            'assignCommercial'     => __('Asignar comercial', 'garantias-online-360vo'),
            'assignCommercialTitle'=> __('Asignar comercial', 'garantias-online-360vo'),
            'assignCommercialTitleTemplate' => __('Asignar comercial a %s', 'garantias-online-360vo'),
            'assignCommercialDescription' => __('Selecciona el comercial que gestionará a %s.', 'garantias-online-360vo'),
            'manageCommercials'    => __('Gestionar comerciales', 'garantias-online-360vo'),
            'assignCommercialSearchPlaceholder' => __('Buscar comercial por nombre o email…', 'garantias-online-360vo'),
            'assignCommercialLoading' => __('Buscando comerciales…', 'garantias-online-360vo'),
            'assignCommercialEmpty' => __('No se han encontrado comerciales con ese criterio.', 'garantias-online-360vo'),
            'assignCommercialDirectoryEmpty' => __('No hay comerciales disponibles para asignar.', 'garantias-online-360vo'),
            'assignCommercialSave' => __('Guardar cambios', 'garantias-online-360vo'),
            'assignCommercialSaving' => __('Guardando…', 'garantias-online-360vo'),
            'assignCommercialSaved' => __('Cambios guardados', 'garantias-online-360vo'),
            'assignCommercialError' => __('No se ha podido completar la operación. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'assignCommercialAssignedTitle' => __('Comerciales actualmente asignados a %s', 'garantias-online-360vo'),
            'assignCommercialAssignedEmpty' => __('No hay comerciales asignados actualmente.', 'garantias-online-360vo'),
            'assignCommercialRemove' => __('Retirar comercial de %s', 'garantias-online-360vo'),
            'assignCommercialClientsSingular' => __('1 cliente asignado', 'garantias-online-360vo'),
            'assignCommercialClientsPlural' => __('clientes asignados', 'garantias-online-360vo'),
            'assignCommercialSelectAction' => __('Seleccionar', 'garantias-online-360vo'),
            'assignCommercialSelectedAction' => __('Seleccionado', 'garantias-online-360vo'),
            'assignCommercialRemoveAction' => __('Quitar', 'garantias-online-360vo'),
            'manageOffers'         => __('Gestionar ofertas', 'garantias-online-360vo'),
            'manageOffersTitle'    => __('Gestionar ofertas', 'garantias-online-360vo'),
            'manageOffersTitleTemplate' => __('Gestionar ofertas de %s', 'garantias-online-360vo'),
            'manageSepa'           => __('Gestionar SEPA', 'garantias-online-360vo'),
            'manageSepaTitle'      => __('Gestionar SEPA', 'garantias-online-360vo'),
            'manageSepaTitleTemplate' => __('Gestionar SEPA de %s', 'garantias-online-360vo'),
            'dialogSave'           => __('Guardar cambios', 'garantias-online-360vo'),
            'close'                => __('Cerrar', 'garantias-online-360vo'),
        ],
        'icons' => [
            'email' => Svg::icon('email'),
            'phone' => Svg::icon('phone'),
            'arrowDown' => Svg::icon('arrow_drop_down'),
            'arrowUp' => Svg::icon('arrow_drop_up'),
            'personAdd' => Svg::icon('person_add'),
            'close' => Svg::icon('cerrar'),
            'search' => Svg::icon('search'),
            'manageOffers' => Svg::icon('manage_offers'),
            'manageSepa' => Svg::icon('payment'),
        ],
    ];
    ?>
    <script>
        window.__GO_CLIENTES__ = <?php echo wp_json_encode($clients_config); ?>;
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/clientes.min.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>
<?php endif; ?>

<?php if (($is_add_guarantee ?? false)) : ?>
    <?php
    // Asegura que las variables existen (y previene errores)
    if (!isset($current_user) || !($current_user instanceof WP_User)) {
        $current_user = wp_get_current_user();
    }
    $roles = (array) $current_user->roles;
    $is_admin = $is_admin ?? user_can($current_user, 'manage_options');
    $is_comercial = $is_comercial ?? in_array('go_comercial', $roles, true);
    $is_director = in_array('go_director_comercial', $roles, true);
    $is_garantias = in_array('go_garantias', $roles, true);
    $icon_percent_html = Svg::icon('percent');
    $icon_warning_html = Svg::icon('warning');
    $icon_check_html = Svg::icon('check');
    $icon_pdf_html = Svg::icon('pdf');
    $icon_save_html = Svg::icon('save');
    $reclamation_url = ReclamationDocument::get_url();

    $current_user_labels = UserProfileResolver::get_vendor_labels((int) $current_user->ID);
    $current_user_company_name = (string) ($current_user_labels['company_name'] ?? '');
    $current_user_company_type_label = '';
    if (! empty($current_user_labels['company']['type']['label'])) {
        $current_user_company_type_label = (string) $current_user_labels['company']['type']['label'];
    }

    if ($is_admin) {
        $js_user_role = 'admin';
    } elseif (in_array('go_profesional', (array)$current_user->roles, true)) {
        $js_user_role = 'go_profesional';
    } elseif (in_array('go_particular', (array)$current_user->roles, true)) {
        $js_user_role = 'go_particular';
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
                        } elseif (in_array('go_profesional', $roles, true)) {
                            echo 'go_profesional';
                        } elseif (in_array('go_particular', $roles, true)) {
                            echo 'go_particular';
                        } elseif ($is_garantias) {
                            echo 'go_garantias';
                        } elseif ($is_director) {
                            echo 'go_director_comercial';
                        } elseif ($is_comercial) {
                            echo 'go_comercial';
                        } else {
                            echo 'user';
                        }
                        ?>",
                currentUserId: <?php echo (int) get_current_user_id(); ?>,
                companyName: "<?php echo esc_js($current_user_company_name); ?>",
                companyTypeLabel: "<?php echo esc_js($current_user_company_type_label); ?>"
            },
            icons: {
                percent: `<?php echo addslashes($icon_percent_html); ?>`,
                check: `<?php echo addslashes($icon_check_html); ?>`,
                warning: `<?php echo addslashes($icon_warning_html); ?>`,
                pdf: `<?php echo addslashes($icon_pdf_html); ?>`,
                save: `<?php echo addslashes($icon_save_html); ?>`
            },
            pages: {
                misGarantias: "<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>",
                nuevaGarantia: "<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>"
            },
            documents: {
                reclamacion: "<?php echo esc_url($reclamation_url); ?>"
            },
            features: {
                exampleData: <?php echo $example_data_enabled ? 'true' : 'false'; ?>
            }
        };
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/pdf-lib.min.js', GARANTIAS360VO__FILE__)); ?>"></script>
    <script src="<?php echo esc_url(plugins_url('assets/js/nueva_garantia.min.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>

<?php endif; ?>

<?php if (! empty($is_register_page) && empty($is_login_page)) : ?>
    <script
        src="<?php echo esc_url(plugins_url('assets/js/register.min.js', GARANTIAS360VO__FILE__)); ?>"
        type="module"
        defer></script>
<?php endif; ?>

<?php if ($is_account_page ?? false) : ?>
    <?php
    $account_config = [
        'rest'     => [
            'endpoint' => esc_url_raw(rest_url('go/v1/account')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ],
        'strings'  => [
            'saving'  => __('Guardando cambios…', 'garantias-online-360vo'),
            'success' => __('Cambios guardados correctamente.', 'garantias-online-360vo'),
            'error'   => __('No se han podido guardar los cambios. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'invalid' => __('Revisa los datos introducidos e inténtalo de nuevo.', 'garantias-online-360vo'),
            'dirty'   => __('Tienes cambios sin guardar.', 'garantias-online-360vo'),
        ],
    ];
    ?>
    <script>
        window.go360Account = <?php echo wp_json_encode($account_config); ?>;
    </script>
    <script
        src="<?php echo esc_url(plugins_url('assets/js/account.min.js', GARANTIAS360VO__FILE__)); ?>"
        defer></script>
<?php endif; ?>






</body>

</html>