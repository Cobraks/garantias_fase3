<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Account\AccountViewModel;
use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\Notifications\Email\EmailMessage;
use GarantiasOnline360VO\Notifications\Email\EmailSettings;
use GarantiasOnline360VO\Notifications\Email\Mailer;
use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use GarantiasOnline360VO\Register\SepaMandateService;
use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class AccountRestController
{
    private const NAMESPACE = 'go/v1';
    private const REST_BASE = 'account';

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'update_account'],
                    'permission_callback' => [__CLASS__, 'can_update_account'],
                ],
            ]
        );
    }

    public static function can_update_account(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $user = wp_get_current_user();

        return $user instanceof WP_User && in_array('go_profesional', (array) $user->roles, true);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public static function update_account(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error(
                'go_account_forbidden',
                __('No tienes permisos para actualizar esta información.', 'garantias-online-360vo'),
                ['status' => 403]
            );
        }

        $notifications_data = self::get_array_param($request, 'notifications');
        $workshop_data      = self::get_array_param($request, 'workshop');
        $payments_data      = self::get_array_param($request, 'payments');
        $is_admin_user      = current_user_can('manage_options');
        $admin_data         = $is_admin_user ? self::get_array_param($request, 'admin') : [];

        $notifications = self::update_notifications($user_id, $notifications_data);
        if (is_wp_error($notifications)) {
            return $notifications;
        }

        $workshop = self::update_workshop($user_id, $workshop_data);
        if (is_wp_error($workshop)) {
            return $workshop;
        }

        $profile_image = self::update_profile_image($user_id, $request);
        if (is_wp_error($profile_image)) {
            return $profile_image;
        }

        $payments = self::update_payments($user_id, $payments_data, $request);
        if (is_wp_error($payments)) {
            return $payments;
        }

        $admin_response = null;
        if ($is_admin_user) {
            $admin_response = [];

            if (array_key_exists('notifications', $admin_data)) {
                $admin_notifications = self::update_admin_notifications($admin_data['notifications']);
                if (is_wp_error($admin_notifications)) {
                    return $admin_notifications;
                }
                $admin_response['notifications'] = $admin_notifications;
            } else {
                $admin_response['notifications'] = self::get_admin_notifications_snapshot();
            }

            if (array_key_exists('transfer', $admin_data)) {
                $admin_transfer = self::update_admin_transfer($admin_data['transfer']);
                if (is_wp_error($admin_transfer)) {
                    return $admin_transfer;
                }
                $admin_response['transfer'] = $admin_transfer;
            } else {
                $admin_response['transfer'] = self::get_admin_transfer_snapshot();
            }

            $should_update_documents = array_key_exists('documents', $admin_data) || self::admin_document_has_upload($request);
            if ($should_update_documents) {
                $admin_documents = self::update_admin_documents($admin_data['documents'] ?? [], $request);
                if (is_wp_error($admin_documents)) {
                    return $admin_documents;
                }
                $admin_response['documents'] = $admin_documents;
            } else {
                $admin_response['documents'] = self::get_admin_documents_snapshot();
            }
        }

        $resolved_notification = NotificationEmailResolver::resolve_with_details($user_id);

        $response = [
            'success'       => true,
            'notifications' => array_merge(
                $notifications,
                [
                    'resolved_email'     => $resolved_notification['email'],
                    'uses_registration'  => $resolved_notification['uses_registration'],
                ]
            ),
            'workshop'      => $workshop,
            'profile_image' => $profile_image,
            'payments'      => $payments,
        ];

        if ($admin_response !== null) {
            $response['admin'] = $admin_response;
        }

        return rest_ensure_response($response);
    }

    /**
     * @param int                      $user_id
     * @param array<string, mixed>|mixed $data
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function update_notifications(int $user_id, $data)
    {
        $data = is_array($data) ? $data : [];
        $raw_email = isset($data['email']) ? (string) $data['email'] : '';
        $email = sanitize_email($raw_email);
        $use_registration = array_key_exists('use_registration', $data)
            ? (bool) $data['use_registration']
            : ($email === '');

        if (! $use_registration && $raw_email !== '' && ! is_email($email)) {
            return new WP_Error(
                'go_account_invalid_notification_email',
                __('El correo de notificaciones no es válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if ($use_registration) {
            $email = '';
        }

        $scope = 'user_' . $user_id;
        $group = self::get_acf_group($scope, 'ajustes_de_notificaciones');
        $group['misma_direccion_registro'] = $use_registration ? 1 : 0;
        $group['correo_electronico_notificaciones'] = $email;

        if (function_exists('update_field')) {
            update_field('ajustes_de_notificaciones', $group, $scope);
        }

        update_user_meta($user_id, 'ajustes_de_notificaciones_misma_direccion_registro', $use_registration ? 1 : 0);
        update_user_meta($user_id, 'ajustes_de_notificaciones_correo_electronico_notificaciones', $email);
        update_user_meta($user_id, 'ajustes_de_notificaciones_correo_electronico', $email);

        return [
            'email'            => $email,
            'use_registration' => $use_registration,
        ];
    }

    /**
     * @param int                      $user_id
     * @param array<string, mixed>|mixed $data
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function update_workshop(int $user_id, $data)
    {
        $data = is_array($data) ? $data : [];
        $has_workshop = ! empty($data['has_workshop']);

        $fields = [
            'name'           => self::sanitize_text($data['name'] ?? ''),
            'fiscal_name'    => self::sanitize_text($data['fiscal_name'] ?? ''),
            'tax_id'         => self::sanitize_text($data['tax_id'] ?? ''),
            'contact_person' => self::sanitize_text($data['contact_person'] ?? ''),
            'phone'          => self::sanitize_text($data['phone'] ?? ''),
            'address'        => self::sanitize_text($data['address'] ?? ''),
            'email'          => '',
        ];

        $raw_email = isset($data['email']) ? (string) $data['email'] : '';
        $email = sanitize_email($raw_email);
        if ($has_workshop && $raw_email !== '' && ! is_email($email)) {
            return new WP_Error(
                'go_account_invalid_workshop_email',
                __('El correo del taller no es válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }
        $fields['email'] = $has_workshop ? $email : '';

        if (! $has_workshop) {
            foreach ($fields as $key => $value) {
                $fields[$key] = '';
            }
        }

        $scope = 'user_' . $user_id;
        $services = self::get_acf_group($scope, 'servicios');
        $workshop_group = [];
        if (isset($services['taller']) && is_array($services['taller'])) {
            $workshop_group = $services['taller'];
        }

        $workshop_group['tiene_taller'] = $has_workshop ? 1 : 0;
        $workshop_group['nombre_taller'] = $fields['name'];
        $workshop_group['denominacion_fiscal'] = $fields['fiscal_name'];
        $workshop_group['cif_taller'] = $fields['tax_id'];
        $workshop_group['persona_contacto_taller'] = $fields['contact_person'];
        $workshop_group['telefono_taller'] = $fields['phone'];
        $workshop_group['correo_taller'] = $fields['email'];
        $workshop_group['direccion_taller'] = $fields['address'];

        $services['taller'] = $workshop_group;

        if (function_exists('update_field')) {
            update_field('servicios', $services, $scope);
        }

        update_user_meta($user_id, 'servicios_taller_tiene_taller', $has_workshop ? 1 : 0);
        update_user_meta($user_id, 'servicios_taller_nombre_taller', $fields['name']);
        update_user_meta($user_id, 'servicios_taller_denominacion_fiscal', $fields['fiscal_name']);
        update_user_meta($user_id, 'servicios_taller_cif_taller', $fields['tax_id']);
        update_user_meta($user_id, 'servicios_taller_persona_contacto_taller', $fields['contact_person']);
        update_user_meta($user_id, 'servicios_taller_telefono_taller', $fields['phone']);
        update_user_meta($user_id, 'servicios_taller_correo_taller', $fields['email']);
        update_user_meta($user_id, 'servicios_taller_direccion_taller', $fields['address']);

        $fields['has_workshop'] = $has_workshop;

        return $fields;
    }

    /**
     * @param array<string, mixed>|mixed $data
     *
     * @return array{recipients: array<int, array{email:string,bcc:bool}>, reply_to:string}|WP_Error
     */
    private static function update_admin_notifications($data)
    {
        $data = is_array($data) ? $data : [];

        $raw_recipients = isset($data['recipients']) && is_array($data['recipients'])
            ? $data['recipients']
            : [];

        $recipients = [];

        foreach ($raw_recipients as $row) {
            if (! is_array($row)) {
                continue;
            }

            $raw_email = isset($row['email']) ? (string) $row['email'] : '';
            $email = sanitize_email($raw_email);
            $bcc = ! empty($row['bcc']);

            if ($raw_email === '' && ! $bcc) {
                continue;
            }

            if ($raw_email === '' || ! is_email($email)) {
                return new WP_Error(
                    'go_account_admin_invalid_notification_email',
                    __('Introduce un correo electrónico válido para las notificaciones de administración.', 'garantias-online-360vo'),
                    ['status' => 400]
                );
            }

            $recipients[] = [
                'admin_recipients' => $email,
                'copia_oculta'     => $bcc ? 1 : 0,
            ];
        }

        $reply_raw = isset($data['reply_to']) ? (string) $data['reply_to'] : '';
        $reply_to = sanitize_email($reply_raw);

        if ($reply_raw !== '' && ! is_email($reply_to)) {
            return new WP_Error(
                'go_account_admin_invalid_reply_to',
                __('La dirección de respuesta no es válida.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $options = self::get_option_group('notificaciones');
        $notifications_group = [];
        if (isset($options['notificaciones_email']) && is_array($options['notificaciones_email'])) {
            $notifications_group = $options['notificaciones_email'];
        }

        $notifications_group['direcciones_correo'] = array_values($recipients);
        $notifications_group['direccion_respuesta'] = $reply_to;

        $options['notificaciones_email'] = $notifications_group;

        self::update_option_field('notificaciones', $options);
        self::update_option_field('notificaciones_notificaciones_email', $notifications_group);
        self::update_option_field('notificaciones_notificaciones_email_direccion_respuesta', $reply_to);
        update_option('options_notificaciones_notificaciones_email_direcciones_correo', array_values($recipients));
        update_option('options_notificaciones_notificaciones_email_direccion_respuesta', $reply_to);

        $public_recipients = array_map(
            static function ($row) {
                return [
                    'email' => isset($row['admin_recipients']) ? (string) $row['admin_recipients'] : '',
                    'bcc'   => ! empty($row['copia_oculta']),
                ];
            },
            array_values($recipients)
        );

        return [
            'recipients' => $public_recipients,
            'reply_to'   => $reply_to,
        ];
    }

    /**
     * @param array<string, mixed>|mixed $data
     *
     * @return array{iban:string}
     */
    private static function update_admin_transfer($data): array
    {
        $data = is_array($data) ? $data : [];
        $raw_iban = isset($data['iban']) ? (string) $data['iban'] : '';
        $iban = strtoupper(self::sanitize_text($raw_iban));
        $iban = preg_replace('/\s+/', ' ', trim($iban));

        $options = self::get_option_group('datos_bancarios');
        $options['iban_360vo'] = $iban;

        self::update_option_field('datos_bancarios', $options);
        self::update_option_field('datos_bancarios_iban_360vo', $iban);

        if ($iban === '') {
            delete_option('options_datos_bancarios_iban_360vo');
        } else {
            update_option('options_datos_bancarios_iban_360vo', $iban);
        }

        return [
            'iban' => $iban,
        ];
    }

    /**
     * @param array<string, mixed>|mixed $data
     *
     * @return array{claim_procedure: array<string, mixed>}|WP_Error
     */
    private static function update_admin_documents($data, WP_REST_Request $request)
    {
        $data = is_array($data) ? $data : [];

        $documents = self::get_option_group('documentacion');
        $current_attachment = self::resolve_option_attachment($documents['procedimiento_de_reclamacion'] ?? null);

        $files = $request->get_file_params();
        $claim_file = is_array($files) ? ($files['admin_claim_document'] ?? null) : null;

        if (is_array($claim_file) && empty($claim_file['tmp_name'])) {
            $claim_file = null;
        }

        if ($claim_file && ! empty($claim_file['error'])) {
            return new WP_Error(
                'go_account_admin_document_upload',
                __('No se ha podido subir el procedimiento de reclamación.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $claim_data = isset($data['claim_procedure']) && is_array($data['claim_procedure'])
            ? $data['claim_procedure']
            : [];

        $remove_claim = ! empty($claim_data['remove']) && ! $claim_file;

        if (! $claim_file && ! $remove_claim) {
            return self::get_admin_documents_snapshot();
        }

        $attachment_id = $current_attachment;

        if ($claim_file) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $overrides = ['test_form' => false];
            $handled = wp_handle_upload($claim_file, $overrides);

            if (! is_array($handled) || isset($handled['error'])) {
                return new WP_Error(
                    'go_account_admin_document_upload',
                    $handled['error'] ?? __('Error al subir el procedimiento de reclamación.', 'garantias-online-360vo'),
                    ['status' => 400]
                );
            }

            $attachment = [
                'post_mime_type' => $handled['type'] ?? 'application/pdf',
                'post_title'     => sanitize_file_name($claim_file['name'] ?? 'procedimiento-reclamacion'),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];

            $new_attachment_id = wp_insert_attachment($attachment, $handled['file']);
            if (is_wp_error($new_attachment_id) || ! $new_attachment_id) {
                if (isset($handled['file']) && file_exists($handled['file'])) {
                    @unlink($handled['file']);
                }

                return new WP_Error(
                    'go_account_admin_document_upload',
                    __('No se ha podido guardar el procedimiento de reclamación.', 'garantias-online-360vo'),
                    ['status' => 500]
                );
            }

            $metadata = wp_generate_attachment_metadata($new_attachment_id, $handled['file']);
            wp_update_attachment_metadata($new_attachment_id, $metadata);

            $attachment_id = (int) $new_attachment_id;
        } elseif ($remove_claim) {
            $attachment_id = 0;
        }

        if ($attachment_id > 0) {
            $documents['procedimiento_de_reclamacion'] = $attachment_id;
        } else {
            $documents['procedimiento_de_reclamacion'] = null;
        }

        self::update_option_field('documentacion', $documents);

        if ($attachment_id > 0) {
            self::update_option_field('documentacion_procedimiento_de_reclamacion', $attachment_id);
            update_option('options_documentacion_procedimiento_de_reclamacion', $attachment_id);
        } else {
            self::update_option_field('documentacion_procedimiento_de_reclamacion', null);
            delete_option('options_documentacion_procedimiento_de_reclamacion');
        }

        return self::get_admin_documents_snapshot();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private static function update_profile_image(int $user_id, WP_REST_Request $request)
    {
        $files = $request->get_file_params();
        $profile_file = is_array($files) ? ($files['profile_image'] ?? null) : null;

        if (! is_array($profile_file) || empty($profile_file['tmp_name'])) {
            return self::prepare_profile_image_response($user_id, self::resolve_profile_image_id($user_id));
        }

        if (! empty($profile_file['error'])) {
            return new WP_Error(
                'go_account_profile_image_upload',
                __('No se ha podido subir la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = ['test_form' => false];
        $handled = wp_handle_upload($profile_file, $overrides);

        if (! is_array($handled) || isset($handled['error'])) {
            return new WP_Error(
                'go_account_profile_image_upload',
                $handled['error'] ?? __('Error al subir la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $attachment = [
            'post_mime_type' => $handled['type'] ?? 'image/jpeg',
            'post_title'     => sanitize_file_name($profile_file['name'] ?? 'profile-image'),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $handled['file']);
        if (is_wp_error($attachment_id) || ! $attachment_id) {
            if (isset($handled['file']) && file_exists($handled['file'])) {
                @unlink($handled['file']);
            }

            return new WP_Error(
                'go_account_profile_image_upload',
                __('No se ha podido guardar la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $handled['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        update_user_meta($user_id, 'profile_image', (int) $attachment_id);
        if (function_exists('update_field')) {
            update_field('profile_image', (int) $attachment_id, 'user_' . $user_id);
        }

        return self::prepare_profile_image_response($user_id, (int) $attachment_id);
    }

    /**
     * @param array<string, mixed>|mixed $data
     * @return array<string, mixed>|WP_Error
     */
    private static function update_payments(int $user_id, $data, WP_REST_Request $request)
    {
        $data = is_array($data) ? $data : [];
        $sepa = isset($data['sepa']) && is_array($data['sepa']) ? $data['sepa'] : [];
        $signed = isset($sepa['signed']) && is_array($sepa['signed']) ? $sepa['signed'] : [];

        $files = $request->get_file_params();
        $signed_file = is_array($files) ? ($files['account_sepa_signed'] ?? null) : null;
        if (is_array($signed_file) && empty($signed_file['tmp_name'])) {
            $signed_file = null;
        }

        $has_upload = $signed_file && (! array_key_exists('upload', $signed) || ! empty($signed['upload']));
        $has_remove = ! empty($signed['remove']) && ! $signed_file;

        if (! $has_upload && ! $has_remove) {
            return self::build_payments_snapshot($user_id);
        }

        if ($has_upload && is_array($signed_file)) {
            $result = self::handle_signed_upload($user_id, $signed_file);
            if (is_wp_error($result)) {
                return $result;
            }
        } elseif ($has_remove) {
            SepaMandateService::clear_signed_mandate($user_id);
        }

        return self::build_payments_snapshot($user_id);
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>|WP_Error
     */
    private static function handle_signed_upload(int $user_id, array $file)
    {
        if (! isset($file['tmp_name']) || ! is_string($file['tmp_name']) || $file['tmp_name'] === '') {
            return new WP_Error(
                'go_account_sepa_upload',
                __('No se ha podido procesar el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if (! empty($file['error'])) {
            return new WP_Error(
                'go_account_sepa_upload',
                __('No se ha podido subir el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size > 5 * 1024 * 1024) {
            return new WP_Error(
                'go_account_sepa_size',
                __('El mandato SEPA firmado supera el tamaño permitido (5MB).', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if (! function_exists('wp_check_filetype_and_ext')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $original_name = isset($file['name']) ? (string) $file['name'] : 'mandato-sepa-firmado.pdf';
        $check = wp_check_filetype_and_ext($file['tmp_name'], $original_name, ['pdf' => 'application/pdf']);
        if (! is_array($check) || ($check['ext'] ?? '') !== 'pdf') {
            return new WP_Error(
                'go_account_sepa_type',
                __('El mandato SEPA debe estar en formato PDF.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $binary = file_get_contents($file['tmp_name']);
        if (! is_string($binary) || $binary === '') {
            return new WP_Error(
                'go_account_sepa_binary',
                __('No se ha podido leer el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $filename = sanitize_file_name($original_name);
        if ($filename === '') {
            $filename = 'mandato-sepa-firmado.pdf';
        }
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.pdf';
        } elseif (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            $filename = sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME) ?: 'mandato-sepa-firmado') . '.pdf';
        }

        $pending_meta = SepaMandateService::get_document_meta($user_id, SepaMandateService::TYPE_PENDING);
        $context = [
            'filename'     => $filename,
            'generated_at' => gmdate('c'),
        ];
        if (! empty($pending_meta['reference'])) {
            $context['reference'] = $pending_meta['reference'];
        }

        $stored = SepaMandateService::store_signed_mandate($user_id, $binary, $context);
        if (is_wp_error($stored)) {
            return $stored;
        }

        $stored['submitted_at'] = current_time('timestamp');

        self::notify_admin_signed_mandate($user_id, $stored, $binary);
        self::log_sepa_signed_upload($user_id, $stored);

        return $stored;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function notify_admin_signed_mandate(int $user_id, array $document, string $binary): void
    {
        $delivery = self::resolve_admin_recipients();
        if (empty($delivery['to']) && empty($delivery['bcc'])) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return;
        }

        $profile = UserProfileResolver::build_from_user($user);
        $company_name = $profile['company']['name'] ?? '';
        $personal_name = $profile['personal_full_name'] ?? $profile['personal_name'] ?? $user->display_name;
        $profile_url = self::build_user_profile_url($user_id);
        $phone = get_user_meta($user_id, 'datos_usuario_telefono', true);
        if (! is_string($phone)) {
            $phone = '';
        }

        $filename = isset($document['filename']) ? sanitize_file_name((string) $document['filename']) : 'mandato-sepa-firmado.pdf';
        if ($filename === '') {
            $filename = 'mandato-sepa-firmado.pdf';
        }
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.pdf';
        }

        if (! function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $temporary_files = [];
        $attachments = [];

        $temp_dir = trailingslashit(get_temp_dir());
        $tmp_file = '';
        if ($temp_dir !== '' && is_dir($temp_dir) && is_writable($temp_dir)) {
            $unique = wp_unique_filename($temp_dir, $filename);
            if ($unique !== '') {
                $tmp_file = $temp_dir . $unique;
            }
        }
        if ($tmp_file === '') {
            $tmp_file = wp_tempnam($filename);
        }

        if ($tmp_file && file_put_contents($tmp_file, $binary) !== false) {
            $attachments[] = [
                'file' => $tmp_file,
                'name' => $filename,
                'type' => 'application/pdf',
            ];
            $temporary_files[] = $tmp_file;
        }

        $submitted_at = isset($document['submitted_at']) ? (int) $document['submitted_at'] : current_time('timestamp');
        $reference = isset($document['reference']) ? (string) $document['reference'] : '';

        $renderer = new TemplateRenderer();
        $context = [
            'user' => [
                'name'        => $personal_name,
                'email'       => $profile['email'] ?? $user->user_email,
                'phone'       => $phone,
                'company'     => $company_name,
                'profile_url' => $profile_url,
            ],
            'document' => [
                'filename'   => $filename,
                'reference'  => $reference,
                'submitted'  => $submitted_at,
            ],
            'signature' => EmailSettings::getSignature(),
        ];

        $body = $renderer->render('sepa-signed-admin', $context);
        if ($body === '') {
            foreach ($temporary_files as $file) {
                if (is_string($file) && file_exists($file)) {
                    @unlink($file);
                }
            }
            return;
        }

        $subject_name = $company_name !== '' ? $company_name : $personal_name;
        $subject = sprintf(
            __('SEPA firmado recibido: %s', 'garantias-online-360vo'),
            $subject_name !== '' ? $subject_name : __('Profesional', 'garantias-online-360vo')
        );

        $headers = [];
        $from_header = EmailSettings::buildFromHeader('admin');
        if ($from_header !== '') {
            $headers[] = $from_header;
        }

        $mailer = new Mailer();
        $message = new EmailMessage(
            $delivery['to'] ?: [$delivery['primary']],
            $subject,
            $body,
            $headers,
            $attachments,
            [
                'bcc'      => $delivery['bcc'],
                'reply_to' => $delivery['reply_to'],
            ]
        );

        $mailer->send($message);

        foreach ($temporary_files as $file) {
            if (is_string($file) && file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{primary:string,to:array<int,string>,bcc:array<int,string>,reply_to:string}
     */
    private static function resolve_admin_recipients(): array
    {
        $primary = sanitize_email((string) get_option('admin_email'));
        $to = [];
        $bcc = [];
        $reply_to = '';

        if (function_exists('get_field')) {
            $settings = get_field('notificaciones', SettingsPage::SUBMENU_SLUG);
            if (! is_array($settings)) {
                $settings = [];
            }
            $notifications = $settings['notificaciones_email'] ?? get_field('notificaciones_email', SettingsPage::SUBMENU_SLUG);
            if (! is_array($notifications)) {
                $notifications = [];
            }
            $rows = $settings['direcciones_correo'] ?? ($notifications['direcciones_correo'] ?? []);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $email = sanitize_email($row['correo'] ?? '');
                    $type  = sanitize_key($row['destino'] ?? '');
                    if (! is_email($email)) {
                        continue;
                    }
                    if ($type === 'bcc') {
                        $bcc[] = $email;
                    } else {
                        $to[] = $email;
                    }
                }
            }
            $reply_to_candidate = sanitize_email($settings['direccion_respuesta'] ?? ($notifications['direccion_respuesta'] ?? ''));
            if (is_email($reply_to_candidate)) {
                $reply_to = $reply_to_candidate;
            }
        }

        if (empty($to) && is_email($primary)) {
            $to[] = $primary;
        }

        return [
            'primary'  => is_email($primary) ? $primary : '',
            'to'       => array_values(array_unique(array_filter($to, 'is_email'))),
            'bcc'      => array_values(array_unique(array_filter($bcc, 'is_email'))),
            'reply_to' => $reply_to,
        ];
    }

    private static function build_user_profile_url(int $user_id): string
    {
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return '';
        }

        $slug = $user->user_nicename !== '' ? $user->user_nicename : $user->user_login;
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return '';
        }

        return trailingslashit(home_url('/garantias-online/clientes/' . rawurlencode($slug)));
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function log_sepa_signed_upload(int $user_id, array $document): void
    {
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return;
        }

        $profile = UserProfileResolver::build_from_user($user);
        $context = [
            'user_id'           => $user_id,
            'user_name'         => $profile['personal_full_name'] ?? $profile['personal_name'] ?? $user->display_name,
            'user_email'        => $profile['email'] ?? $user->user_email,
            'company_name'      => $profile['company']['name'] ?? '',
            'document_name'     => isset($document['filename']) ? (string) $document['filename'] : '',
            'document_reference'=> isset($document['reference']) ? (string) $document['reference'] : '',
        ];

        ActivityLogger::log('sepa.signed_uploaded', [
            'actor_id'   => $user_id,
            'target_type'=> 'user',
            'target_id'  => $user_id,
            'context'    => $context,
        ]);
    }

    private static function build_payments_snapshot(int $user_id): array
    {
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return [];
        }

        $account = AccountViewModel::from_user($user);

        return is_array($account['payments'] ?? null)
            ? $account['payments']
            : [];
    }

    private static function get_array_param(WP_REST_Request $request, string $key): array
    {
        $value = $request->get_param($key);
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $json = $request->get_json_params();
        if (is_array($json) && isset($json[$key]) && is_array($json[$key])) {
            return $json[$key];
        }

        return [];
    }

    private static function resolve_profile_image_id(int $user_id): int
    {
        $scope = 'user_' . $user_id;
        $attachment_id = 0;

        if (function_exists('get_field')) {
            $field_value = get_field('profile_image', $scope);
            if (is_array($field_value)) {
                if (isset($field_value['ID'])) {
                    $attachment_id = (int) $field_value['ID'];
                } elseif (isset($field_value['id'])) {
                    $attachment_id = (int) $field_value['id'];
                }
            } elseif ($field_value) {
                $attachment_id = (int) $field_value;
            }
        }

        if (! $attachment_id) {
            $attachment_id = (int) get_user_meta($user_id, 'profile_image', true);
        }

        return $attachment_id;
    }

    /**
     * @return array{id:int,url:string,filename:string}
     */
    private static function prepare_profile_image_response(int $user_id, int $attachment_id): array
    {
        if ($attachment_id <= 0) {
            return [
                'id'       => 0,
                'url'      => get_avatar_url($user_id, ['size' => 256]),
                'filename' => '',
            ];
        }

        $url = wp_get_attachment_image_url($attachment_id, [256, 256]);
        if (! $url) {
            $url = wp_get_attachment_url($attachment_id) ?: '';
        }

        $file = get_attached_file($attachment_id);

        return [
            'id'       => $attachment_id,
            'url'      => $url,
            'filename' => $file ? basename($file) : '',
        ];
    }

    private static function get_admin_notifications_snapshot(): array
    {
        $options = self::get_option_group('notificaciones');
        $notifications_group = [];
        if (isset($options['notificaciones_email']) && is_array($options['notificaciones_email'])) {
            $notifications_group = $options['notificaciones_email'];
        }

        $recipients = [];
        if (isset($notifications_group['direcciones_correo']) && is_array($notifications_group['direcciones_correo'])) {
            foreach ($notifications_group['direcciones_correo'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $email = sanitize_email((string) ($row['admin_recipients'] ?? ''));
                if ($email === '') {
                    continue;
                }

                $recipients[] = [
                    'email' => $email,
                    'bcc'   => ! empty($row['copia_oculta']),
                ];
            }
        }

        $reply_to = '';
        if (isset($notifications_group['direccion_respuesta'])) {
            $reply_to = sanitize_email((string) $notifications_group['direccion_respuesta']);
        }

        if ($reply_to === '') {
            $fallback = get_option('options_notificaciones_notificaciones_email_direccion_respuesta');
            if (is_string($fallback) && $fallback !== '') {
                $reply_to = sanitize_email($fallback);
            }
        }

        return [
            'recipients' => $recipients,
            'reply_to'   => $reply_to,
        ];
    }

    private static function get_admin_transfer_snapshot(): array
    {
        $options = self::get_option_group('datos_bancarios');
        $iban = '';

        if (isset($options['iban_360vo'])) {
            $iban = self::sanitize_text($options['iban_360vo']);
        }

        if ($iban === '') {
            $single = get_option('options_datos_bancarios_iban_360vo');
            if (is_string($single) && $single !== '') {
                $iban = self::sanitize_text($single);
            }
        }

        return [
            'iban' => $iban,
        ];
    }

    private static function get_admin_documents_snapshot(): array
    {
        $options = self::get_option_group('documentacion');
        $attachment_id = self::resolve_option_attachment($options['procedimiento_de_reclamacion'] ?? null);

        if ($attachment_id <= 0) {
            $fallback = get_option('options_documentacion_procedimiento_de_reclamacion');
            if (is_numeric($fallback)) {
                $attachment_id = (int) $fallback;
            }
        }

        return [
            'claim_procedure' => self::prepare_claim_document_response($attachment_id),
        ];
    }

    private static function admin_document_has_upload(WP_REST_Request $request): bool
    {
        $files = $request->get_file_params();
        if (! is_array($files)) {
            return false;
        }

        $file = $files['admin_claim_document'] ?? null;
        if (! is_array($file)) {
            return false;
        }

        return ! empty($file['tmp_name']);
    }

    private static function resolve_option_attachment($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            if (isset($value['ID'])) {
                return (int) $value['ID'];
            }

            if (isset($value['id'])) {
                return (int) $value['id'];
            }

            if (isset($value['value'])) {
                return (int) $value['value'];
            }
        } elseif ($value instanceof \WP_Post) {
            return (int) $value->ID;
        }

        return 0;
    }

    private static function prepare_claim_document_response(int $attachment_id): array
    {
        if ($attachment_id <= 0) {
            return [
                'id'       => 0,
                'title'    => '',
                'filename' => '',
                'url'      => '',
                'size'     => '',
                'linkText' => __('Ver documento', 'garantias-online-360vo'),
            ];
        }

        $title = get_the_title($attachment_id);
        $url = wp_get_attachment_url($attachment_id) ?: '';
        $file = get_attached_file($attachment_id);
        $filename = $file ? basename($file) : '';
        $size = '';

        if ($file && file_exists($file)) {
            $filesize = filesize($file);
            if (is_numeric($filesize)) {
                $size = size_format((float) $filesize);
            }
        }

        return [
            'id'       => $attachment_id,
            'title'    => $title ? (string) $title : $filename,
            'filename' => $filename,
            'url'      => $url,
            'size'     => $size,
            'linkText' => __('Ver documento', 'garantias-online-360vo'),
        ];
    }

    private static function get_option_group(string $field): array
    {
        if (function_exists('get_field')) {
            $value = get_field($field, SettingsPage::SUBMENU_SLUG);
            if (is_array($value)) {
                return $value;
            }
        }

        $option = get_option('options_' . $field);

        return is_array($option) ? $option : [];
    }

    private static function update_option_field(string $field, $value): void
    {
        if (function_exists('update_field')) {
            update_field($field, $value, SettingsPage::SUBMENU_SLUG);
        }

        if ($value === null) {
            delete_option('options_' . $field);
            return;
        }

        update_option('options_' . $field, $value);
    }

    private static function get_acf_group(string $scope, string $field): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        $group = get_field($field, $scope);

        return is_array($group) ? $group : [];
    }

    private static function sanitize_text($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return sanitize_text_field((string) $value);
        }

        return '';
    }
}
