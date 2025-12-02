<?php if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Docs\ReclamationDocument;
use GarantiasOnline360VO\Register\SepaMandateService;
use GarantiasOnline360VO\Support\FeatureFlags;
use GarantiasOnline360VO\Support\UserProfileResolver;
use GarantiasOnline360VO\Rest\GuaranteeRestController;

$example_data_enabled = false;
if (! empty($is_add_guarantee)) {
    $example_data_enabled = FeatureFlags::is_example_data_enabled();
}

$notifications_allowed_roles = ['go_director_comercial', 'go_garantias'];
$notifications_user = is_user_logged_in() ? wp_get_current_user() : null;
$notifications_roles = $notifications_user instanceof \WP_User ? (array) $notifications_user->roles : [];
$notifications_role_match = array_intersect($notifications_allowed_roles, $notifications_roles);
$can_manage_notifications = current_user_can('manage_options') || ! empty($notifications_role_match);
?>
</div> <!-- /.main-grid -->
</div> <!-- /.container -->
<footer class="footer">
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
                <li><a href="https://www.360vo.es/aviso-legal/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Aviso Legal', 'garantias-online-360vo'); ?></a></li>
                <li><a href="https://www.360vo.es/politica-de-privacidad/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Política de Privacidad', 'garantias-online-360vo'); ?></a></li>
                <li><a href="https://www.360vo.es/aviso-legal/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Términos y Condiciones', 'garantias-online-360vo'); ?></a></li>
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
    if (!isset($current_user) || !($current_user instanceof \WP_User)) {
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
    } elseif (in_array('go_particular', $roles, true) || in_array('go_individual', $roles, true)) {
        $js_user_role = 'go_particular';
    } elseif (in_array('go_garantias', $roles, true)) {
        $js_user_role = 'go_garantias';
    } elseif ($is_director) {
        $js_user_role = 'go_director_comercial';
    } elseif ($is_comercial) {
        $js_user_role = 'go_comercial';
    } else {
        $js_user_role = 'user';
    }
    $list_cache_generation = class_exists(GuaranteeRestController::class)
        ? GuaranteeRestController::get_list_cache_generation_snapshot()
        : 1;
    $proforma_settings = class_exists(GuaranteeRestController::class)
        ? GuaranteeRestController::get_proforma_feature_settings()
        : [];
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
                role: "<?php echo esc_js($js_user_role); ?>",
                name: "<?php echo esc_js($display_name ?? ''); ?>"
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
            proforma: <?php echo wp_json_encode($proforma_settings); ?>,
            features: {
                exampleData: <?php echo $example_data_enabled ? 'true' : 'false'; ?>
            },
            cache: {
                guaranteesListVersion: <?php echo (int) $list_cache_generation; ?>
            }
        };
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/mis_garantias.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>
<?php endif; ?>

<?php if ($is_breakdowns_page ?? false) : ?>
    <script src="<?php echo esc_url(plugins_url('assets/js/averias.js', GARANTIAS360VO__FILE__)); ?>" defer></script>
<?php endif; ?>

<?php if ($is_clients_page ?? false) : ?>
    <?php
    if (!isset($current_user) || !($current_user instanceof \WP_User)) {
        $current_user = wp_get_current_user();
    }

    $is_admin = current_user_can('manage_options');
    $current_roles = $current_user instanceof \WP_User ? (array) $current_user->roles : [];
    $has_client_manager_role = in_array('go_director_comercial', $current_roles, true)
        || in_array('go_garantias', $current_roles, true);
    $can_assign_commercials = $is_admin || $has_client_manager_role;
    $can_manage_offers = $is_admin;
    $can_manage_sepa = $is_admin;
    $can_view_admin_link = $is_admin;

    $clients_config = [
        'rest' => [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ],
        'pagination' => [
            'perPage' => 20,
        ],
        'permissions' => [
            'canAssignCommercials' => $can_assign_commercials,
            'canManageOffers'      => $can_manage_offers,
            'canManageSepa'        => $can_manage_sepa,
            'canViewAdminLink'     => $can_view_admin_link,
        ],
        'router' => [
            'basePath' => trailingslashit(wp_make_link_relative(home_url('/garantias-online/clientes/'))),
        ],
        'presence' => [
            'pollInterval' => 15000,
        ],
        'sepa' => SepaMandateService::get_frontend_config(),
        'strings' => [
            'profile'              => __('Perfil', 'garantias-online-360vo'),
            'client'               => __('Cliente', 'garantias-online-360vo'),
            'noResults'            => __('No se han encontrado clientes con los filtros actuales.', 'garantias-online-360vo'),
            'offersEmpty'          => __('Sin ofertas activas', 'garantias-online-360vo'),
            'commercialsEmpty'     => __('Sin comercial asignado', 'garantias-online-360vo'),
            'detailTitle'          => __('Detalles del cliente', 'garantias-online-360vo'),
            'detailLoading'        => __('Cargando datos del cliente…', 'garantias-online-360vo'),
            'selectPrompt'         => __('Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.', 'garantias-online-360vo'),
            'quickFilterCountSingular' => __('cliente', 'garantias-online-360vo'),
            'quickFilterCountPlural'   => __('clientes', 'garantias-online-360vo'),
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
            'offersFixedPrice'     => __('Precio fijo', 'garantias-online-360vo'),
            'monthsSuffix'         => __('meses', 'garantias-online-360vo'),
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
            'adminLink'            => __('Edita en panel de administración WordPress', 'garantias-online-360vo'),
            'offersBadgeSingular'  => __('1 oferta activa', 'garantias-online-360vo'),
            'offersBadgePlural'    => __('%s ofertas activas', 'garantias-online-360vo'),
            'commercialsBadgeEmpty' => __('Sin comerciales asignados', 'garantias-online-360vo'),
            'commercialsBadgeSingular' => __('1 comercial asignado', 'garantias-online-360vo'),
            'commercialsBadgePlural' => __('%s comerciales asignados', 'garantias-online-360vo'),
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
            'manageOffersIntro'    => __('Configura las ofertas disponibles para este cliente y guarda los cambios para aplicarlos.', 'garantias-online-360vo'),
            'manageOffersLoading'  => __('Cargando ofertas…', 'garantias-online-360vo'),
            'manageOffersFetchError' => __('No se han podido cargar las ofertas. Actualiza la página e inténtalo de nuevo.', 'garantias-online-360vo'),
            'online'               => __('Online', 'garantias-online-360vo'),
            'manageOffersEmptyState' => __('No hay ofertas configuradas para este cliente.', 'garantias-online-360vo'),
            'manageOffersAdd'      => __('Añadir nueva oferta', 'garantias-online-360vo'),
            'manageOffersAddPercent' => __('Porcentaje descuento / sin suplementos', 'garantias-online-360vo'),
            'manageOffersAddFixed' => __('Precio fijo', 'garantias-online-360vo'),
            'manageOffersCardTitle'=> __('Oferta', 'garantias-online-360vo'),
            'manageOffersTypeLabel'=> __('Tipo de oferta', 'garantias-online-360vo'),
            'manageOffersTypePlaceholder' => __('Selecciona un tipo…', 'garantias-online-360vo'),
            'manageOffersTypeError' => __('Selecciona un tipo de oferta.', 'garantias-online-360vo'),
            'manageOffersNameLabel'=> __('Nombre personalizado', 'garantias-online-360vo'),
            'manageOffersNamePlaceholder' => __('Introduce el nombre de la oferta', 'garantias-online-360vo'),
            'manageOffersNameError' => __('Introduce un nombre para esta oferta.', 'garantias-online-360vo'),
            'manageOffersDiscountLabel' => __('Porcentaje de descuento', 'garantias-online-360vo'),
            'manageOffersDiscountPlaceholder' => __('Ej. 10', 'garantias-online-360vo'),
            'manageOffersDiscountError' => __('Introduce un porcentaje entre 1 y 100.', 'garantias-online-360vo'),
            'manageOffersScopeLabel' => __('Ámbito de aplicación', 'garantias-online-360vo'),
            'manageOffersScopePlaceholder' => __('Selecciona un ámbito…', 'garantias-online-360vo'),
            'manageOffersScopeError' => __('Selecciona un ámbito de aplicación.', 'garantias-online-360vo'),
            'manageOffersModalitiesLabel' => __('Modalidades incluidas', 'garantias-online-360vo'),
            'manageOffersModalitiesEmpty' => __('No hay modalidades disponibles.', 'garantias-online-360vo'),
            'manageOffersModalitiesError' => __('Selecciona al menos una modalidad.', 'garantias-online-360vo'),
            'manageOffersExpiryLabel' => __('Caducidad', 'garantias-online-360vo'),
            'manageOffersExpiryError' => __('Introduce una fecha válida.', 'garantias-online-360vo'),
            'manageOffersStatusLabel' => __('Oferta activa', 'garantias-online-360vo'),
            'manageOffersStatusActive' => __('Activa', 'garantias-online-360vo'),
            'manageOffersStatusInactive' => __('Inactiva', 'garantias-online-360vo'),
            'manageOffersSinSuplementosNote' => __('No se aplicarán suplementos cuando esta oferta esté activa.', 'garantias-online-360vo'),
            'manageOffersFixedTypeLabel' => __('Tipo de garantía', 'garantias-online-360vo'),
            'manageOffersFixedTypePlaceholder' => __('Selecciona un ámbito…', 'garantias-online-360vo'),
            'manageOffersFixedLevelLabel' => __('Cobertura', 'garantias-online-360vo'),
            'manageOffersFixedLevelPlaceholder' => __('Selecciona una cobertura…', 'garantias-online-360vo'),
            'manageOffersFixedPriceLabel' => __('Precio fijo', 'garantias-online-360vo'),
            'manageOffersFixedPricePlaceholder' => __('Ej. 120', 'garantias-online-360vo'),
            'manageOffersFixedDurationLabel' => __('Duración máxima', 'garantias-online-360vo'),
            'manageOffersFixedDurationPlaceholder' => __('Selecciona una duración…', 'garantias-online-360vo'),
            'manageOffersFixedExcludeLabel' => __('Excluir resto de coberturas', 'garantias-online-360vo'),
            'manageOffersFixedExcludeTitle' => __('Coberturas disponibles', 'garantias-online-360vo'),
            'manageOffersFixedExcludeNote' => __('Activado: Solo muestra la cobertura seleccionada. Desactivado: Permite seleccionar el resto de coberturas', 'garantias-online-360vo'),
            'manageOffersFixedTypeError' => __('Selecciona un tipo de garantía.', 'garantias-online-360vo'),
            'manageOffersFixedLevelError' => __('Selecciona una cobertura.', 'garantias-online-360vo'),
            'manageOffersFixedPriceError' => __('Introduce un precio válido.', 'garantias-online-360vo'),
            'manageOffersFixedDurationError' => __('Selecciona una duración.', 'garantias-online-360vo'),
            'manageOffersDuplicate' => __('Duplicar', 'garantias-online-360vo'),
            'manageOffersDelete'    => __('Eliminar', 'garantias-online-360vo'),
            'manageOffersSaving'    => __('Guardando…', 'garantias-online-360vo'),
            'manageOffersSaved'     => __('Ofertas actualizadas correctamente.', 'garantias-online-360vo'),
            'manageOffersError'     => __('No se ha podido guardar las ofertas. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'manageOffersValidationError' => __('Revisa los campos marcados para continuar.', 'garantias-online-360vo'),
            'manageSepa'           => __('Gestionar SEPA', 'garantias-online-360vo'),
            'manageSepaTitle'      => __('Gestionar SEPA', 'garantias-online-360vo'),
            'manageSepaTitleTemplate' => __('Gestionar SEPA de %s', 'garantias-online-360vo'),
            'manageSepaPendingTitle' => __('Mandato pendiente de firma', 'garantias-online-360vo'),
            'manageSepaPendingDescription' => __('Descarga el mandato generado y solicítale al profesional que lo firme.', 'garantias-online-360vo'),
            'manageSepaSignedTitle' => __('Mandato firmado por el profesional', 'garantias-online-360vo'),
            'manageSepaSignedDescription' => __('Revisa el documento adjunto antes de activar la domiciliación bancaria.', 'garantias-online-360vo'),
            'manageSepaPendingStatus' => __('Pendiente de firma', 'garantias-online-360vo'),
            'manageSepaValidationStatus' => __('Pendiente de validación', 'garantias-online-360vo'),
            'manageSepaActivationStatus' => __('Pendiente de domiciliación', 'garantias-online-360vo'),
            'manageSepaNoDocuments' => __('No hay documentos SEPA disponibles.', 'garantias-online-360vo'),
            'manageSepaDownload'   => __('Descargar mandato', 'garantias-online-360vo'),
            'manageSepaViewSigned' => __('Ver mandato firmado', 'garantias-online-360vo'),
            'manageSepaSignedUploadTitle' => __('Mandato firmado', 'garantias-online-360vo'),
            'manageSepaSignedUploadDescription' => __('Sube el mandato firmado recibido del profesional para continuar con la activación.', 'garantias-online-360vo'),
            'manageSepaSignedUploadPlaceholder' => __('Selecciona un archivo PDF…', 'garantias-online-360vo'),
            'manageSepaSignedUploadSelected' => __('Archivo seleccionado: %s', 'garantias-online-360vo'),
            'manageSepaSignedUploadButton' => __('Subir mandato firmado', 'garantias-online-360vo'),
            'manageSepaSignedUploadLoading' => __('Subiendo…', 'garantias-online-360vo'),
            'manageSepaSignedUploadHelp' => __('Formato PDF, máximo 5 MB.', 'garantias-online-360vo'),
            'manageSepaSignedUploadError' => __('No se ha podido subir el mandato SEPA firmado. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'manageSepaActivate'   => __('Activar domiciliación bancaria', 'garantias-online-360vo'),
            'manageSepaActivateHelp' => __('Confirma la activación únicamente cuando el mandato firmado sea correcto.', 'garantias-online-360vo'),
            'manageSepaConfirmTitle' => __('Confirmar SEPA', 'garantias-online-360vo'),
            'manageSepaConfirmMessage' => __('Confirmo que %s nos ha enviado el mandato SEPA firmado y todos los datos son correctos.', 'garantias-online-360vo'),
            'manageSepaConfirmAccept' => __('Activar domiciliación bancaria', 'garantias-online-360vo'),
            'manageSepaConfirmCancel' => __('Cancelar', 'garantias-online-360vo'),
            'manageSepaConfirmNote' => __('El método de pago por domiciliación bancaria se activará.', 'garantias-online-360vo'),
            'manageSepaConfirmError' => __('No se ha podido activar la domiciliación bancaria. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'manageSepaConfirmLoading' => __('Activando…', 'garantias-online-360vo'),
            'manageSepaConfirmCheckbox' => __('He revisado esta información y confirmo la operación.', 'garantias-online-360vo'),
            'manageSepaConfirmActorFallback' => __('este profesional', 'garantias-online-360vo'),
            'manageSepaConfirmReferenceLabel' => __('Referencia', 'garantias-online-360vo'),
            'manageSepaGenerateTitle' => __('Generar mandato SEPA', 'garantias-online-360vo'),
            'manageSepaGenerateDescription' => __('Rellena los datos para generar el mandato SEPA de %s.', 'garantias-online-360vo'),
            'manageSepaGenerateHelp' => __('Enviaremos el mandato por correo electrónico y quedará disponible en su área privada.', 'garantias-online-360vo'),
            'manageSepaGenerateButton' => __('Generar SEPA', 'garantias-online-360vo'),
            'manageSepaGenerateLoading' => __('Generando mandato…', 'garantias-online-360vo'),
            'manageSepaGenerateSuccess' => __('Mandato SEPA generado y enviado al profesional.', 'garantias-online-360vo'),
            'manageSepaGenerateError' => __('No se ha podido generar el mandato SEPA. Revisa los datos e inténtalo de nuevo.', 'garantias-online-360vo'),
            'manageSepaGeneratePostal' => __('Introduce un código postal válido.', 'garantias-online-360vo'),
            'manageSepaGenerateIban' => __('Introduce un IBAN válido.', 'garantias-online-360vo'),
            'manageSepaGenerateSwift' => __('Introduce un código SWIFT/BIC válido.', 'garantias-online-360vo'),
            'manageSepaDeactivate' => __('Deshabilitar domiciliación bancaria', 'garantias-online-360vo'),
            'manageSepaDeactivateHelp' => __('Deshabilita la domiciliación bancaria cuando el cliente quiera volver a gestionar los cobros manualmente.', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmTitle' => __('Deshabilitar SEPA', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmMessage' => __('Confirmo que vamos a deshabilitar la domiciliación bancaria a %s.', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmAccept' => __('Deshabilitar domiciliación bancaria', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmError' => __('No se ha podido deshabilitar la domiciliación bancaria. Inténtalo de nuevo.', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmLoading' => __('Deshabilitando…', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmCheckbox' => __('He revisado esta información y confirmo la operación.', 'garantias-online-360vo'),
            'manageSepaDeactivateConfirmNote' => __('El método de pago por domiciliación bancaria se desactivará.', 'garantias-online-360vo'),
            'manageSepaDeactivateReasonLabel' => __('Notas', 'garantias-online-360vo'),
            'manageSepaDeactivateReasonPlaceholder' => __('Añade una nota…', 'garantias-online-360vo'),
            'manageSepaDeactivateReasonHelp' => __('Esta nota es privada y solo la verán los administradores.', 'garantias-online-360vo'),
            'manageSepaDeactivateReasonError' => __('Introduce una nota.', 'garantias-online-360vo'),
            'manageSepaDisabledNotice' => __('La domiciliación bancaria está deshabilitada. Revisa el motivo antes de reactivarla.', 'garantias-online-360vo'),
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
            'save' => Svg::icon('save'),
            'pdf' => Svg::icon('pdf'),
        ],
    ];
    ?>
    <?php if ($can_manage_sepa) : ?>
        <div
            class="confirm-modal confirm-modal--clients"
            data-sepa-confirm-modal
            aria-hidden="true"
            hidden
        >
            <div
                class="confirm-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="client-sepa-confirm-title"
                tabindex="-1"
            >
                <button
                    type="button"
                    class="confirm-modal__close"
                    aria-label="<?php esc_attr_e('Cerrar confirmación', 'garantias-online-360vo'); ?>"
                >
                    &times;
                </button>
                <div class="confirm-modal__intro">
                    <h2 id="client-sepa-confirm-title" class="confirm-modal__title"><?php esc_html_e('Confirmar SEPA', 'garantias-online-360vo'); ?></h2>
                    <p class="confirm-modal__subtitle"></p>
                    <p class="confirm-modal__message"></p>
                    <p class="confirm-modal__note"><?php esc_html_e('El método de pago por domiciliación bancaria se activará.', 'garantias-online-360vo'); ?></p>
                </div>
                <p class="confirm-modal__error" role="alert" hidden></p>
                <div class="confirm-modal__field" data-confirm-reason hidden>
                    <label class="confirm-modal__field-label" for="client-sepa-confirm-reason"><?php esc_html_e('Notas', 'garantias-online-360vo'); ?></label>
                    <textarea id="client-sepa-confirm-reason" class="confirm-modal__textarea" rows="3"></textarea>
                    <p class="confirm-modal__field-help"></p>
                </div>
                <?php
                $client_confirm_checkbox_id   = uniqid('confirm-modal-checkbox-');
                $client_confirm_checkbox_name = $client_confirm_checkbox_id . '-field';
                ?>
                <label class="confirm-modal__checkbox">
                    <input
                        type="checkbox"
                        class="confirm-modal__checkbox-input"
                        id="<?php echo esc_attr($client_confirm_checkbox_id); ?>"
                        name="<?php echo esc_attr($client_confirm_checkbox_name); ?>"
                    >
                    <span class="confirm-modal__checkbox-label"><?php esc_html_e('He revisado esta información y confirmo la operación.', 'garantias-online-360vo'); ?></span>
                </label>
                <div class="confirm-modal__actions">
                    <button type="button" class="confirm-modal__btn confirm-modal__btn--cancel"><?php esc_html_e('Cancelar', 'garantias-online-360vo'); ?></button>
                    <button type="button" class="confirm-modal__btn confirm-modal__btn--confirm" disabled><?php esc_html_e('Activar domiciliación bancaria', 'garantias-online-360vo'); ?></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <script>
        window.__GO_CLIENTES__ = <?php echo wp_json_encode($clients_config); ?>;
    </script>
    <?php if ($can_manage_sepa) : ?>
        <script src="<?php echo esc_url(plugins_url('assets/js/pdf-lib.min.js', GARANTIAS360VO__FILE__)); ?>"></script>
    <?php endif; ?>
    <script src="<?php echo esc_url(plugins_url('assets/js/clientes.min.js', GARANTIAS360VO__FILE__)); ?>" type="module" defer></script>
<?php endif; ?>

<?php if (($is_add_guarantee ?? false)) : ?>
    <?php
    // Asegura que las variables existen (y previene errores)
    if (!isset($current_user) || !($current_user instanceof \WP_User)) {
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
    $icon_advantage_html = Svg::icon('advantage');
    $icon_disadvantage_html = Svg::icon('disadvantage');
    $icon_pdf_html = Svg::icon('pdf');
    $icon_save_html = Svg::icon('save');
    $reclamation_url = ReclamationDocument::get_url();
    $proforma_settings = GuaranteeRestController::get_proforma_feature_settings();

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
    } elseif (
        in_array('go_particular', (array)$current_user->roles, true) ||
        in_array('go_individual', (array)$current_user->roles, true)
    ) {
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
                        } elseif (
                            in_array('go_particular', $roles, true) ||
                            in_array('go_individual', $roles, true)
                        ) {
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
                name: "<?php echo esc_js($display_name ?? ''); ?>",
                companyName: "<?php echo esc_js($current_user_company_name); ?>",
                companyTypeLabel: "<?php echo esc_js($current_user_company_type_label); ?>"
            },
            icons: {
                percent: `<?php echo addslashes($icon_percent_html); ?>`,
                check: `<?php echo addslashes($icon_check_html); ?>`,
                advantage: `<?php echo addslashes($icon_advantage_html); ?>`,
                disadvantage: `<?php echo addslashes($icon_disadvantage_html); ?>`,
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
            },
            proforma: {
                enabled: <?php echo ! empty($proforma_settings['enabled']) ? 'true' : 'false'; ?>,
                allowedRoles: <?php echo wp_json_encode($proforma_settings['allowed_roles'] ?? []); ?>,
                professionalPaymentModes: <?php echo wp_json_encode($proforma_settings['professional_payment_modes'] ?? []); ?>,
                highlightTotal: <?php echo ! empty($proforma_settings['highlight_total']) ? 'true' : 'false'; ?>,
                options: <?php echo wp_json_encode($proforma_settings['options'] ?? []); ?>
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
    $push_config = null;
    if ($can_manage_notifications) {
        $push_config = [
            'publicKey'             => apply_filters('go360/push/public_key', ''),
            'subscriptionEndpoint'  => esc_url_raw(rest_url('go/v1/push-subscriptions')),
            'notificationsEndpoint' => esc_url_raw(rest_url('go/v1/push-notifications')),
            'testEndpoint'          => esc_url_raw(rest_url('go/v1/push-notifications/test')),
            'testIcon'              => esc_url_raw(plugins_url('assets/images/logo-notify.png', GARANTIAS360VO__FILE__)),
            'serviceWorker'         => esc_url_raw(plugins_url('assets/js/push-sw.js', GARANTIAS360VO__FILE__)),
        ];
    }

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
            'sepaAwaitingValidation' => __('Pendiente de validación', 'garantias-online-360vo'),
            'sepaAwaitingSignature'  => __('Pendiente de firma', 'garantias-online-360vo'),
            'sepaAwaitingMessage'    => __('Tu SEPA firmado está pendiente de validación.', 'garantias-online-360vo'),
            'sepaAwaitingActivation' => __('Pendiente de domiciliación', 'garantias-online-360vo'),
            'sepaGenerateError'      => __('No se ha podido generar el mandato SEPA. Revisa los datos e inténtalo de nuevo.', 'garantias-online-360vo'),
            'sepaGenerateSuccess'    => __('Mandato SEPA generado correctamente. Descárgalo para firmarlo.', 'garantias-online-360vo'),
            'sepaGenerateLoading'    => __('Generando mandato…', 'garantias-online-360vo'),
        ],
        'sepa'     => SepaMandateService::get_frontend_config(),
    ];
    if ($push_config !== null) {
        $account_config['push'] = $push_config;
    }
    ?>
    <script>
        window.go360Account = <?php echo wp_json_encode($account_config); ?>;
    </script>
    <script src="<?php echo esc_url(plugins_url('assets/js/pdf-lib.min.js', GARANTIAS360VO__FILE__)); ?>"></script>
    <script
        src="<?php echo esc_url(plugins_url('assets/js/account.min.js', GARANTIAS360VO__FILE__)); ?>"
        defer></script>
    <?php if ($push_config !== null) : ?>
        <script
            src="<?php echo esc_url(plugins_url('assets/js/push-subscription.min.js', GARANTIAS360VO__FILE__)); ?>"
            defer></script>
    <?php endif; ?>
<?php endif; ?>

<?php if (is_user_logged_in()) : ?>
    <?php
    $heartbeat_interval = (int) apply_filters('go360/client_presence/heartbeat_interval', 60);
    if ($heartbeat_interval < 15) {
        $heartbeat_interval = 15;
    }

    $idle_timeout = (int) apply_filters('go360/client_presence/idle_timeout', 120);
    if ($idle_timeout < $heartbeat_interval) {
        $idle_timeout = $heartbeat_interval;
    }

    $presence_config = [
        'restRoot'    => esc_url_raw(rest_url()),
        'nonce'       => wp_create_nonce('wp_rest'),
        'interval'    => $heartbeat_interval,
        'idleTimeout' => $idle_timeout,
    ];
    ?>
    <script>
        (() => {
            const config = <?php echo wp_json_encode($presence_config); ?>;
            if (typeof window === 'undefined' || typeof document === 'undefined') {
                return;
            }

            if (typeof fetch !== 'function') {
                return;
            }

            const restRoot = typeof config.restRoot === 'string' ? config.restRoot : '';
            const restNonce = typeof config.nonce === 'string' ? config.nonce : '';

            if (!restRoot || !restNonce) {
                return;
            }

            const intervalMs = Math.max(15000, Math.floor(Number(config.interval) * 1000) || 60000);
            const idleTimeoutMs = Math.max(intervalMs, Math.floor(Number(config.idleTimeout) * 1000) || 120000);
            const endpoint = `${restRoot.replace(/\/$/, '')}/go/v1/clientes/status/heartbeat`;

            let heartbeatTimer = null;
            let idleTimer = null;
            let active = false;
            let lastStatus = '';

            const sendHeartbeat = (status, options = {}) => {
                const normalized = status === 'inactive' ? 'inactive' : 'active';

                if (!options.force && normalized === 'inactive' && lastStatus === 'inactive') {
                    return Promise.resolve();
                }

                const body = JSON.stringify({ status: normalized });
                const headers = { 'Content-Type': 'application/json' };
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const request = fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body,
                    keepalive: Boolean(options.keepalive),
                }).then(() => {
                    lastStatus = normalized;
                }).catch(() => {
                    // Silenciar errores de red en el latido.
                });

                return request;
            };

            const clearHeartbeatTimer = () => {
                if (heartbeatTimer) {
                    window.clearTimeout(heartbeatTimer);
                    heartbeatTimer = null;
                }
            };

            const scheduleHeartbeat = () => {
                clearHeartbeatTimer();
                if (!active) {
                    return;
                }

                heartbeatTimer = window.setTimeout(() => {
                    sendHeartbeat('active');
                    scheduleHeartbeat();
                }, intervalMs);
            };

            const markIdle = () => {
                if (!active) {
                    return;
                }

                active = false;
                clearHeartbeatTimer();
                sendHeartbeat('inactive', { force: true });
            };

            const scheduleIdleTimer = () => {
                if (idleTimer) {
                    window.clearTimeout(idleTimer);
                }

                idleTimer = window.setTimeout(() => {
                    markIdle();
                }, idleTimeoutMs);
            };

            const markActive = (options = {}) => {
                const wasActive = active;
                active = true;

                scheduleIdleTimer();
                scheduleHeartbeat();

                if (!wasActive || options.immediate) {
                    sendHeartbeat('active', { force: true });
                }
            };

            const activityHandler = () => {
                markActive();
            };

            ['mousemove', 'keydown', 'scroll', 'click', 'touchstart', 'touchend'].forEach((eventName) => {
                document.addEventListener(eventName, activityHandler, { passive: true });
            });

            window.addEventListener('focus', activityHandler);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    markActive({ immediate: true });
                }
            });

            window.addEventListener('beforeunload', () => {
                sendHeartbeat('inactive', { keepalive: true, force: true });
            });

            if (!document.hidden) {
                markActive({ immediate: true });
            } else {
                scheduleIdleTimer();
            }
        })();
    </script>
<?php endif; ?>

<?php if ($can_manage_notifications) :
    $notification_categories = \GarantiasOnline360VO\Notifications\NotificationCategories::allowedDefinitions();
    ?>
    <script>
        window.go360Notifications = {
            endpoints: {
                list: <?php echo wp_json_encode(esc_url_raw(rest_url('go/v1/push-notifications'))); ?>,
                markAll: <?php echo wp_json_encode(esc_url_raw(rest_url('go/v1/push-notifications'))); ?>,
            },
            nonce: <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
            perPage: 10,
            pollInterval: 4000,
            toastDuration: 8000,
            user: {
                id: <?php echo (int) get_current_user_id(); ?>,
            },
            preferences: {
                toast: true,
                sound: true
            },
            filters: {
                allLabel: <?php echo wp_json_encode(__('Todas', 'garantias-online-360vo')); ?>,
                categories: <?php echo wp_json_encode($notification_categories); ?>
            }
        };
    </script>
    <script
        src="<?php echo esc_url(plugins_url('assets/js/admin-notifications.min.js', GARANTIAS360VO__FILE__)); ?>"
        defer></script>
<?php endif; ?>






</body>

</html>