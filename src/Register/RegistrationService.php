<?php

namespace GarantiasOnline360VO\Register;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\Notifications\Email\EmailMessage;
use GarantiasOnline360VO\Notifications\Email\EmailSettings;
use GarantiasOnline360VO\Notifications\Email\EmailReputationGuard;
use GarantiasOnline360VO\Notifications\Email\Mailer;
use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use GarantiasOnline360VO\Register\SepaMandateService;
use GarantiasOnline360VO\SettingsPage;
use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class RegistrationService
{
    private const CODE_EXPIRATION     = 5 * MINUTE_IN_SECONDS;
    private const CODE_LENGTH         = 6;
    private const MAX_ATTEMPTS        = 5;
    private const LOCK_DURATION       = 5 * MINUTE_IN_SECONDS;
    private const RESEND_LIMIT        = 3;
    private const RESEND_WINDOW       = HOUR_IN_SECONDS;
    private const RESEND_COOLDOWN     = 60;
    private const MAX_IMAGE_SIZE      = 5_242_880; // 5 MB
    private const MAX_SEPA_SIZE       = 5_242_880; // 5 MB

    private const CHANNELS = [
        'compraventa'  => ['label' => 'Compraventa', 'role' => 'go_profesional'],
        'concesionario'=> ['label' => 'Concesionario Oficial', 'role' => 'go_profesional'],
        'individual'   => ['label' => 'Particular', 'role' => 'go_particular'],
        'agency'       => ['label' => 'Gestoría', 'role' => 'go_gestoria'],
    ];

    private Mailer $mailer;
    private TemplateRenderer $renderer;

    public function __construct(?Mailer $mailer = null, ?TemplateRenderer $renderer = null)
    {
        $this->mailer   = $mailer ?: new Mailer();
        $this->renderer = $renderer ?: new TemplateRenderer();
    }

    /**
     * Registra al usuario y envía los correos correspondientes.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return array<string, mixed>|WP_Error
     */
    public function register(array $data, array $files = [])
    {
        $sanitized = $this->validate_registration($data, $files);
        if ($sanitized instanceof WP_Error) {
            return $sanitized;
        }

        $attachments = [];
        $uploaded    = [];

        try {
            $uploaded = $this->handle_uploads($sanitized);
            $user_id  = $this->create_user($sanitized, $uploaded);

            if (is_wp_error($user_id)) {
                $this->cleanup_uploads($uploaded);
                return $user_id;
            }

            $this->persist_profile($user_id, $sanitized, $uploaded);

            $sepa_document = null;
            if ($sanitized['enable_sepa']) {
                $sepa_result = $this->persist_sepa_document($user_id, $sanitized);
                if ($sepa_result instanceof WP_Error) {
                    $this->cleanup_uploads($uploaded);
                    wp_delete_user($user_id);
                    return $sepa_result;
                }
                $sepa_document = $sepa_result;
            }
            $sanitized['sepa_pending_document'] = $sepa_document;

            $verification = $this->issue_verification($user_id, $sanitized['email']);
            if (is_wp_error($verification)) {
                $this->cleanup_uploads($uploaded);
                wp_delete_user($user_id);
                return $verification;
            }

            $admin_result = $this->notify_admin($user_id, $sanitized, $uploaded);

            $user_result  = $this->notify_user($user_id, $sanitized, $verification['code'], $verification['expires']);
            if (! $user_result) {
                ActivityLogger::log('user.register_email_failed', [
                    'actor_id' => $user_id,
                    'event_category' => 'system',
                    'context' => [
                        'user_email'      => $sanitized['email'],
                        'channel'         => $sanitized['channel'],
                        'enable_sepa'     => $sanitized['enable_sepa'],
                        'sepa_reference'  => $sanitized['sepa_reference'] ?? '',
                        'sepa_generated'  => $sanitized['sepa_generated_at'] ?? '',
                        'has_sepa_payload'=> ! empty($sanitized['sepa_pending_document']),
                    ],
                ]);
                $this->cleanup_uploads($uploaded);
                wp_delete_user($user_id);
                return new WP_Error(
                    'go_register_email_failed',
                    __('No se ha podido enviar el correo de verificación. Vuelve a intentarlo en unos minutos.', 'garantias-online-360vo')
                );
            }

            ActivityLogger::log('user.registered', [
                'actor_id' => $user_id,
                'context'  => [
                    'user_email' => $sanitized['email'],
                    'user_role'  => $sanitized['role'],
                    'channel'    => $sanitized['channel'],
                ],
            ]);

            ActivityLogger::log('user.verification_sent', [
                'actor_id' => $user_id,
                'context'  => [
                    'user_email' => $sanitized['email'],
                    'expires_at' => gmdate('c', $verification['expires']),
                ],
            ]);

            if ($admin_result) {
                ActivityLogger::log('user.registration_notified', [
                    'actor_id' => $user_id,
                    'context'  => [
                        'user_email' => $sanitized['email'],
                        'channel'    => $sanitized['channel'],
                    ],
                ]);
            }

            $response = [
                'status'              => 'pending_verification',
                'token'               => $verification['token'],
                'email'               => $sanitized['email'],
                'expires_at'          => gmdate('c', $verification['expires']),
                'expires_in'          => max(0, $verification['expires'] - time()),
                'resend_available_in' => self::RESEND_COOLDOWN,
            ];

            return $response;
        } catch (\Throwable $exception) {
            $this->cleanup_uploads($uploaded);
            return new WP_Error('go_register_exception', $exception->getMessage());
        }
    }

    /**
     * Verifica el código introducido por el usuario.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function verify(string $token, string $code)
    {
        $token = sanitize_text_field($token);
        $code  = trim($code);

        if ($token === '' || $code === '') {
            return new WP_Error('go_verify_invalid', __('Código de verificación inválido.', 'garantias-online-360vo'));
        }

        $user_id = RegistrationMeta::get_token_owner($token);
        if (! $user_id) {
            return new WP_Error('go_verify_unknown', __('No hemos encontrado ninguna cuenta pendiente con ese código.', 'garantias-online-360vo'));
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_Error('go_verify_unknown', __('No hemos encontrado ninguna cuenta pendiente con ese código.', 'garantias-online-360vo'));
        }

        $status = get_user_meta($user_id, RegistrationMeta::STATUS, true);
        if (is_string($status) && strtolower($status) === 'verified') {
            return [
                'status'   => 'already_verified',
                'redirect' => home_url('/garantias-online/'),
            ];
        }

        $locked_until = (int) get_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL, true);
        if ($locked_until > time()) {
            return new WP_Error(
                'go_verify_locked',
                sprintf(
                    __('Has superado el número de intentos. Vuelve a intentarlo en %s segundos.', 'garantias-online-360vo'),
                    $locked_until - time()
                ),
                [
                    'retry_in' => max(0, $locked_until - time()),
                ]
            );
        }

        $hash = (string) get_user_meta($user_id, RegistrationMeta::HASH, true);
        if ($hash === '') {
            return new WP_Error('go_verify_missing', __('No hay ningún código pendiente para esta cuenta.', 'garantias-online-360vo'));
        }

        $expires = (int) get_user_meta($user_id, RegistrationMeta::EXPIRES, true);
        if ($expires && $expires < time()) {
            return new WP_Error('go_verify_expired', __('El código de verificación ha caducado. Solicita uno nuevo.', 'garantias-online-360vo'));
        }

        if (! wp_check_password($code, $hash, $user_id)) {
            $attempts = (int) get_user_meta($user_id, RegistrationMeta::ATTEMPTS, true);
            $attempts++;
            update_user_meta($user_id, RegistrationMeta::ATTEMPTS, $attempts);

            if ($attempts >= self::MAX_ATTEMPTS) {
                update_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL, time() + self::LOCK_DURATION);
            }

            return new WP_Error('go_verify_mismatch', __('El código introducido no es correcto.', 'garantias-online-360vo'));
        }

        delete_user_meta($user_id, RegistrationMeta::HASH);
        delete_user_meta($user_id, RegistrationMeta::EXPIRES);
        delete_user_meta($user_id, RegistrationMeta::TOKEN);
        delete_user_meta($user_id, RegistrationMeta::ATTEMPTS);
        delete_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL);
        delete_user_meta($user_id, RegistrationMeta::RESEND_COUNT);
        delete_user_meta($user_id, RegistrationMeta::RESEND_LAST);
        delete_user_meta($user_id, RegistrationMeta::RESEND_WINDOW);
        RegistrationMeta::mark_required($user_id, false);
        update_user_meta($user_id, RegistrationMeta::VERIFIED_AT, current_time('mysql', true));

        ActivityLogger::log('user.verification_verified', [
            'actor_id' => $user_id,
            'context'  => [
                'user_email' => $user->user_email,
            ],
        ]);

        if ($this->notify_welcome($user_id)) {
            ActivityLogger::log('user.verification_welcome_sent', [
                'actor_id' => $user_id,
                'event_category' => 'communication',
                'context'  => [
                    'user_email' => $user->user_email,
                    'channel'    => get_user_meta($user_id, 'datos_empresa_tipo_profesional', true),
                ],
            ]);
        }

        return [
            'status'   => 'verified',
            'redirect' => home_url('/garantias-online/'),
        ];
    }

    /**
     * Reenvía un código de verificación.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function resend(string $token)
    {
        $token = sanitize_text_field($token);
        if ($token === '') {
            return new WP_Error('go_resend_invalid', __('Solicitud inválida.', 'garantias-online-360vo'));
        }

        $user_id = RegistrationMeta::get_token_owner($token);
        if (! $user_id) {
            return new WP_Error('go_resend_unknown', __('No hemos encontrado ninguna cuenta pendiente.', 'garantias-online-360vo'));
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_Error('go_resend_unknown', __('No hemos encontrado ninguna cuenta pendiente.', 'garantias-online-360vo'));
        }

        $status = get_user_meta($user_id, RegistrationMeta::STATUS, true);
        if (is_string($status) && strtolower($status) === 'verified') {
            return new WP_Error('go_resend_verified', __('Esta cuenta ya está verificada.', 'garantias-online-360vo'));
        }

        $last_sent = (int) get_user_meta($user_id, RegistrationMeta::RESEND_LAST, true);
        if ($last_sent && (time() - $last_sent) < self::RESEND_COOLDOWN) {
            $remaining = self::RESEND_COOLDOWN - (time() - $last_sent);
            return new WP_Error(
                'go_resend_cooldown',
                sprintf(__('Podrás solicitar un nuevo código en %s segundos.', 'garantias-online-360vo'), $remaining),
                ['retry_in' => $remaining]
            );
        }

        $window_start = (int) get_user_meta($user_id, RegistrationMeta::RESEND_WINDOW, true);
        $resend_count = (int) get_user_meta($user_id, RegistrationMeta::RESEND_COUNT, true);
        if (! $window_start || (time() - $window_start) > self::RESEND_WINDOW) {
            $window_start = time();
            $resend_count = 0;
        }

        if ($resend_count >= self::RESEND_LIMIT) {
            $remaining = ($window_start + self::RESEND_WINDOW) - time();
            return new WP_Error(
                'go_resend_limit',
                __('Has alcanzado el número máximo de reenvíos. Inténtalo más tarde.', 'garantias-online-360vo'),
                ['retry_in' => max(0, $remaining)]
            );
        }

        $code   = $this->generate_code();
        $hash   = wp_hash_password($code);
        $expiry = time() + self::CODE_EXPIRATION;

        update_user_meta($user_id, RegistrationMeta::HASH, $hash);
        update_user_meta($user_id, RegistrationMeta::EXPIRES, $expiry);
        update_user_meta($user_id, RegistrationMeta::ATTEMPTS, 0);
        update_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL, 0);
        update_user_meta($user_id, RegistrationMeta::RESEND_LAST, time());
        update_user_meta($user_id, RegistrationMeta::RESEND_COUNT, $resend_count + 1);
        update_user_meta($user_id, RegistrationMeta::RESEND_WINDOW, $window_start);

        $email_sent = $this->notify_user($user_id, [
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'email'      => $user->user_email,
        ], $code, $expiry);

        if (! $email_sent) {
            return new WP_Error('go_resend_failed', __('No hemos podido reenviar el código. Inténtalo de nuevo más tarde.', 'garantias-online-360vo'));
        }

        ActivityLogger::log('user.verification_resent', [
            'actor_id' => $user_id,
            'context'  => [
                'user_email' => $user->user_email,
                'expires_at' => gmdate('c', $expiry),
            ],
        ]);

        return [
            'status'              => 'resent',
            'expires_at'          => gmdate('c', $expiry),
            'expires_in'          => max(0, $expiry - time()),
            'resend_available_in' => self::RESEND_COOLDOWN,
        ];
    }

    /**
     * Obtiene el contexto de verificación para un usuario pendiente a partir del email.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_verification_context(string $email)
    {
        $email = sanitize_email($email);
        if (! is_email($email)) {
            return new WP_Error('go_verify_invalid_email', __('Introduce un correo electrónico válido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        if (! $user instanceof WP_User) {
            return new WP_Error('go_verify_unknown', __('No hemos encontrado ninguna cuenta pendiente de verificación.', 'garantias-online-360vo'), ['status' => 404]);
        }

        $user_id = (int) $user->ID;
        if (! RegistrationMeta::is_verification_required($user_id)) {
            return new WP_Error('go_verify_not_pending', __('Esta cuenta ya está verificada. Inicia sesión para continuar.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $token = get_user_meta($user_id, RegistrationMeta::TOKEN, true);
        $token = is_string($token) ? $token : '';
        if ($token === '') {
            return new WP_Error('go_verify_unknown', __('No hemos encontrado ninguna solicitud pendiente. Inicia el registro de nuevo.', 'garantias-online-360vo'), ['status' => 404]);
        }

        $expires = (int) get_user_meta($user_id, RegistrationMeta::EXPIRES, true);
        $last_sent = (int) get_user_meta($user_id, RegistrationMeta::RESEND_LAST, true);
        $resend_in = 0;
        if ($last_sent && (time() - $last_sent) < self::RESEND_COOLDOWN) {
            $resend_in = self::RESEND_COOLDOWN - (time() - $last_sent);
        }

        return [
            'status'              => 'pending_verification',
            'token'               => $token,
            'email'               => $user->user_email,
            'expires_at'          => $expires ? gmdate('c', $expires) : '',
            'expires_in'          => $expires ? max(0, $expires - time()) : 0,
            'resend_available_in' => $resend_in,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    private function validate_registration(array $data, array $files)
    {
        $nonce = isset($data['go360_register_nonce']) ? (string) $data['go360_register_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'go360_register_user')) {
            return new WP_Error('go_register_nonce', __('La sesión ha caducado. Recarga la página e inténtalo de nuevo.', 'garantias-online-360vo'));
        }

        $channel = isset($data['channel']) ? sanitize_key($data['channel']) : '';
        if (! isset(self::CHANNELS[$channel])) {
            return new WP_Error('go_register_channel', __('Selecciona un canal de venta válido.', 'garantias-online-360vo'));
        }

        $first_name = $this->sanitize_name($data['first_name'] ?? '');
        $last_name  = $this->sanitize_name($data['last_name'] ?? '');
        if ($first_name === '' || $last_name === '') {
            return new WP_Error('go_register_name', __('Introduce tu nombre y apellidos.', 'garantias-online-360vo'));
        }

        $phone = $this->sanitize_phone($data['phone'] ?? '');
        if ($phone === '') {
            return new WP_Error('go_register_phone', __('Introduce un teléfono válido (9 dígitos).', 'garantias-online-360vo'));
        }

        $email = sanitize_email($data['email'] ?? '');
        if (! is_email($email)) {
            return new WP_Error('go_register_email', __('Introduce un correo electrónico válido.', 'garantias-online-360vo'));
        }
        if (email_exists($email)) {
            return new WP_Error('go_register_email_exists', __('Esta cuenta ya está registrada.', 'garantias-online-360vo'));
        }

        $password = (string) ($data['password'] ?? '');
        if ($this->is_weak_password($password)) {
            return new WP_Error('go_register_password', __('La contraseña debe tener al menos 8 caracteres, incluir un número y un símbolo.', 'garantias-online-360vo'));
        }

        $terms = $this->to_bool($data['terms'] ?? false);
        if (! $terms) {
            return new WP_Error('go_register_terms', __('Debes aceptar los términos y condiciones para continuar.', 'garantias-online-360vo'));
        }

        $company = $this->sanitize_company($channel, $data);
        if ($company instanceof WP_Error) {
            return $company;
        }

        $has_workshop = $this->to_bool($data['has_workshop'] ?? false);
        $workshop = $this->sanitize_workshop($has_workshop, $data);
        if ($workshop instanceof WP_Error) {
            return $workshop;
        }

        $has_web = $this->to_bool($data['has_web'] ?? false);
        $web_url = '';
        if ($has_web) {
            $web_url = trim((string) ($data['web_url'] ?? ''));
            if ($web_url === '') {
                return new WP_Error('go_register_web', __('Introduce la URL de tu web con 360VO.', 'garantias-online-360vo'));
            }
            if (! $this->is_valid_url($web_url)) {
                return new WP_Error('go_register_web_url', __('Introduce una URL válida (https://...).', 'garantias-online-360vo'));
            }
        }

        $auto_signature = $this->to_bool($data['auto_signature'] ?? false);
        $enable_sepa    = $this->to_bool($data['enable_sepa'] ?? false);

        $sepa_reference = SepaMandateService::sanitize_reference((string) ($data['sepa_reference'] ?? ''));
        $sepa_generated_at = $this->sanitize_text($data['sepa_generated_at'] ?? '');

        $sepa = $this->sanitize_sepa($enable_sepa, $data, $sepa_reference, $sepa_generated_at);
        if ($sepa instanceof WP_Error) {
            return $sepa;
        }

        $avatar    = $this->extract_file($files, 'avatar');
        $signature = $this->extract_file($files, 'signature');
        $stamp     = $this->extract_file($files, 'stamp');
        $sepa_file = $this->extract_file($files, 'sepa_document');

        if ($auto_signature) {
            if (! $signature || ! $stamp) {
                return new WP_Error('go_register_signature', __('Debes subir la firma y el sello para activar esta opción.', 'garantias-online-360vo'));
            }
        }

        foreach (['avatar' => $avatar, 'signature' => $signature, 'stamp' => $stamp] as $key => $file) {
            if (! $file) {
                continue;
            }
            $validation = $this->validate_image_file($file, $key);
            if ($validation instanceof WP_Error) {
                return $validation;
            }
        }

        if (! $enable_sepa) {
            $sepa_file = null;
            $sepa_reference = '';
            $sepa_generated_at = '';
        } else {
            if (! $sepa_file) {
                return new WP_Error(
                    'go_register_sepa_document',
                    __('No se ha podido generar el mandato SEPA. Revisa los datos e inténtalo de nuevo.', 'garantias-online-360vo')
                );
            }
            $pdf_validation = $this->validate_pdf_file($sepa_file);
            if ($pdf_validation instanceof WP_Error) {
                return $pdf_validation;
            }
        }

        $sanitized = [
            'nonce'        => $nonce,
            'channel'      => $channel,
            'channel_label'=> self::CHANNELS[$channel]['label'],
            'role'         => self::CHANNELS[$channel]['role'],
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'phone'        => $phone,
            'email'        => $email,
            'password'     => $password,
            'terms'        => true,
            'company'      => $company,
            'has_workshop' => $has_workshop,
            'workshop'     => $workshop,
            'has_web'      => $has_web,
            'web_url'      => $web_url,
            'auto_signature'=> $auto_signature,
            'enable_sepa'  => $enable_sepa,
            'sepa'         => $sepa,
            'avatar'       => $avatar,
            'signature'    => $signature,
            'stamp'        => $stamp,
            'sepa_document'=> $sepa_file,
            'sepa_reference'=> $sepa_reference,
            'sepa_generated_at' => $sepa_generated_at,
            'ip'           => $this->detect_ip(),
        ];

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>|null> $uploads
     */
    private function persist_profile(int $user_id, array $data, array $uploads): void
    {
        update_user_meta($user_id, 'first_name', $data['first_name']);
        update_user_meta($user_id, 'last_name', $data['last_name']);
        update_user_meta($user_id, 'nickname', $data['first_name'] . ' ' . $data['last_name']);
        update_user_meta($user_id, 'datos_usuario_telefono', $data['phone']);
        update_user_meta($user_id, RegistrationMeta::REQUIRED, 1);
        update_user_meta($user_id, RegistrationMeta::REGISTRATION_IP, $data['ip']);

        $scope = 'user_' . $user_id;

        if (function_exists('update_field')) {
            $company_group = [
                'tipo_profesional' => [
                    'value' => $data['channel'],
                    'label' => $data['channel_label'],
                ],
                'datos_empresa' => [
                    'nombre_comercial' => $data['company']['trade_name'],
                    'razon_social'     => $data['company']['legal_name'],
                    'cif'              => $data['company']['tax_id'],
                ],
                'direccion_empresa' => [
                    'direccion'     => $data['company']['address']['street'],
                    'codigo_postal' => $data['company']['address']['postal_code'],
                    'poblacion'     => $data['company']['address']['city'],
                    'provincia'     => $data['company']['address']['province'],
                ],
            ];
            update_field('datos_empresa', $company_group, $scope);

            $services = [
                'taller' => [
                    'tiene_taller'         => $data['has_workshop'],
                    'nombre_taller'        => $data['workshop']['name'] ?? '',
                    'denominacion_fiscal'  => $data['workshop']['fiscal_name'] ?? '',
                    'cif_taller'           => $data['workshop']['tax_id'] ?? '',
                    'persona_contacto_taller' => $data['workshop']['contact'] ?? '',
                    'telefono_taller'      => $data['workshop']['phone'] ?? '',
                    'correo_taller'        => $data['workshop']['email'] ?? '',
                    'direccion_taller'     => $data['workshop']['address'] ?? '',
                ],
                'web' => [
                    'web_360' => $data['has_web'],
                    'url_web' => $data['web_url'],
                ],
            ];
            update_field('servicios', $services, $scope);

            $documents = [
                'firma_y_sello' => [
                    'add_firma_sello' => $data['auto_signature'],
                    'firma'           => $uploads['signature']['id'] ?? 0,
                    'sello'           => $uploads['stamp']['id'] ?? 0,
                ],
            ];
            update_field('documentos', $documents, $scope);

            if ($data['enable_sepa']) {
                $this->update_sepa_acf_group($user_id, $data['sepa']);
            }

            if (! empty($uploads['avatar']['id'])) {
                update_field('profile_image', $uploads['avatar']['id'], $scope);
            }

            $this->persist_avatar_letters($user_id, $data);
        }

        update_user_meta($user_id, 'datos_empresa_tipo_profesional', $data['channel']);
        update_user_meta($user_id, 'datos_empresa_tipo_profesional_label', $data['channel_label']);
        update_user_meta($user_id, 'datos_empresa_nombre_comercial', $data['company']['trade_name']);
        update_user_meta($user_id, 'datos_empresa_razon_social', $data['company']['legal_name']);
        update_user_meta($user_id, 'datos_empresa_cif', $data['company']['tax_id']);
        update_user_meta($user_id, 'datos_empresa_direccion', $data['company']['address']['street']);
        update_user_meta($user_id, 'datos_empresa_codigo_postal', $data['company']['address']['postal_code']);
        update_user_meta($user_id, 'datos_empresa_poblacion', $data['company']['address']['city']);
        update_user_meta($user_id, 'datos_empresa_provincia', $data['company']['address']['province']);
        update_user_meta($user_id, 'datos_empresa_pais', $data['company']['address']['country']);

        if ($data['has_workshop']) {
            update_user_meta($user_id, 'servicios_taller_tiene_taller', 1);
            update_user_meta($user_id, 'servicios_taller_nombre_taller', $data['workshop']['name']);
            update_user_meta($user_id, 'servicios_taller_denominacion_fiscal', $data['workshop']['fiscal_name']);
            update_user_meta($user_id, 'servicios_taller_cif_taller', $data['workshop']['tax_id']);
            update_user_meta($user_id, 'servicios_taller_persona_contacto_taller', $data['workshop']['contact']);
            update_user_meta($user_id, 'servicios_taller_telefono_taller', $data['workshop']['phone']);
            update_user_meta($user_id, 'servicios_taller_correo_taller', $data['workshop']['email']);
            update_user_meta($user_id, 'servicios_taller_direccion_taller', $data['workshop']['address']);
        } else {
            update_user_meta($user_id, 'servicios_taller_tiene_taller', 0);
        }

        update_user_meta($user_id, 'servicios_web_web_360', $data['has_web'] ? 1 : 0);
        update_user_meta($user_id, 'servicios_web_url_web', $data['web_url']);

        update_user_meta($user_id, 'documentos_firma_y_sello_add_firma_sello', $data['auto_signature'] ? 1 : 0);
        if (! empty($uploads['signature']['id'])) {
            update_user_meta($user_id, 'documentos_firma_y_sello_firma', $uploads['signature']['id']);
        }
        if (! empty($uploads['stamp']['id'])) {
            update_user_meta($user_id, 'documentos_firma_y_sello_sello', $uploads['stamp']['id']);
        }

        if ($data['enable_sepa']) {
            $sepa = $data['sepa'];
            $this->persist_sepa_meta($user_id, $sepa);
        } else {
            SepaMandateService::set_status($user_id, SepaMandateService::STATUS_UNFILLED);
            SepaMandateService::set_activation_flag($user_id, false, SepaMandateService::ACTIVATION_DISABLED);
            SepaMandateService::set_payment_method($user_id, 'transferencia');
            SepaMandateService::clear_requested_flag($user_id);
        }

        if (! empty($uploads['avatar']['id'])) {
            update_user_meta($user_id, 'profile_image', $uploads['avatar']['id']);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $uploads
     * @return int|WP_Error
     */
    private function create_user(array $data, array $uploads)
    {
        $login = $this->generate_username($data['first_name'], $data['last_name'], $data['company']['trade_name'], $data['email']);

        $user_data = [
            'user_login' => $login,
            'user_pass'  => $data['password'],
            'user_email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'display_name' => $data['first_name'] . ' ' . $data['last_name'],
            'role'         => $data['role'],
        ];

        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        if ($data['role'] !== '' && ! in_array($data['role'], (array) get_userdata($user_id)->roles, true)) {
            wp_update_user([
                'ID'   => $user_id,
                'role' => $data['role'],
            ]);
        }

        if (! empty($uploads['avatar']['id'])) {
            update_user_meta($user_id, 'profile_image', $uploads['avatar']['id']);
        }

        return $user_id;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function handle_uploads(array $data): array
    {
        $uploads = [
            'avatar'    => null,
            'signature' => null,
            'stamp'     => null,
        ];

        foreach (['avatar', 'signature', 'stamp'] as $key) {
            if (! $data[$key]) {
                continue;
            }

            $result = $this->store_image($data[$key]);
            if ($result instanceof WP_Error) {
                throw new \RuntimeException($result->get_error_message());
            }
            $uploads[$key] = $result;
        }

        return $uploads;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    private function persist_sepa_document(int $user_id, array &$data)
    {
        $file = $data['sepa_document'];
        if (! is_array($file) || empty($file['tmp_name']) || ! file_exists($file['tmp_name'])) {
            return new WP_Error('go_register_sepa_document', __('No se ha recibido el mandato SEPA generado.', 'garantias-online-360vo'));
        }

        $binary = file_get_contents($file['tmp_name']);
        if ($binary === '' || $binary === false) {
            return new WP_Error('go_register_sepa_read', __('No se ha podido procesar el mandato SEPA generado.', 'garantias-online-360vo'));
        }

        $context = [
            'filename'     => isset($file['name']) ? sanitize_file_name((string) $file['name']) : 'mandato-sepa.pdf',
            'reference'    => isset($data['sepa']['reference']) ? (string) $data['sepa']['reference'] : ($data['sepa_reference'] ?? ''),
            'generated_at' => isset($data['sepa']['generated_at']) ? (string) $data['sepa']['generated_at'] : ($data['sepa_generated_at'] ?? ''),
        ];

        $stored = SepaMandateService::store_pending_mandate($user_id, $binary, $context);
        if ($stored instanceof WP_Error) {
            return $stored;
        }

        $final_reference = isset($stored['reference']) ? (string) $stored['reference'] : ($context['reference'] ?? '');
        $final_generated = isset($stored['generated_at']) ? (string) $stored['generated_at'] : ($context['generated_at'] ?? '');

        $data['sepa_reference'] = $final_reference;
        $data['sepa_generated_at'] = $final_generated;
        if (isset($data['sepa']) && is_array($data['sepa'])) {
            $data['sepa']['reference'] = $final_reference;
            $data['sepa']['generated_at'] = $final_generated;
        }

        if (! empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            @unlink($file['tmp_name']);
        }

        $data['sepa_document'] = null;

        $normalized_document = SepaMandateService::normalize_document($stored, $user_id, SepaMandateService::TYPE_PENDING);
        $data['sepa_pending_document'] = $normalized_document;

        $this->update_sepa_acf_group($user_id, $data['sepa'] ?? [], $normalized_document);
        $this->persist_sepa_meta($user_id, $data['sepa'] ?? []);
        SepaMandateService::clear_signed_mandate($user_id);

        return $normalized_document;
    }

    /**
     * @param array<string, mixed> $sepa
     * @param array<string, mixed>|null $document
     */
    private function update_sepa_acf_group(int $user_id, array $sepa, ?array $document = null): void
    {
        if (! function_exists('update_field') || ! function_exists('get_field')) {
            return;
        }

        $scope = 'user_' . $user_id;
        $group = get_field('gestion_pagos', $scope);
        if (! is_array($group)) {
            $group = [];
        }

        $gestion_sepa = $group['gestion_sepa'] ?? [];
        if (! is_array($gestion_sepa)) {
            $gestion_sepa = [];
        }

        $debtor = $gestion_sepa['datos_deudor'] ?? [];
        if (! is_array($debtor)) {
            $debtor = [];
        }

        $payment_type = isset($sepa['payment_type']) ? (string) $sepa['payment_type'] : 'recurrente';
        $payment_label = isset($sepa['payment_type_label']) ? (string) $sepa['payment_type_label'] : '';
        if ($payment_label === '') {
            $payment_label = $payment_type === 'unico'
                ? __('Único', 'garantias-online-360vo')
                : __('Recurrente', 'garantias-online-360vo');
        }

        $debtor = array_merge($debtor, [
            'nombre_deudor'    => $sepa['name'] ?? '',
            'direccion_deudor' => $sepa['address'] ?? '',
            'codigo_postal'    => $sepa['postal_code'] ?? '',
            'poblacion'        => $sepa['city'] ?? '',
            'provincia'        => $sepa['state'] ?? '',
            'pais_deudor'      => $sepa['country'] ?? '',
            'swift_bic'        => $sepa['swift'] ?? '',
            'numero_cuenta'    => $sepa['iban'] ?? '',
            'fecha_firma'      => $sepa['signature_date'] ?? '',
            'localidad_firma'  => $sepa['signature_locality'] ?? '',
            'tipo_pago'        => [
                'value' => $payment_type,
                'label' => $payment_label,
            ],
        ]);

        $estado = $gestion_sepa['estado_documentos'] ?? [];
        if (! is_array($estado)) {
            $estado = [];
        }

        $estado['estado_sepa'] = SepaMandateService::build_status_payload(
            SepaMandateService::STATUS_PENDING_SIGNATURE
        );
        $activation_state = $document !== null
            ? SepaMandateService::ACTIVATION_PENDING
            : SepaMandateService::ACTIVATION_DISABLED;
        $activation_payload = SepaMandateService::build_activation_payload($activation_state);
        $estado['activar_sepa'] = $activation_payload['flag'];
        $estado['metodo_de_pago'] = SepaMandateService::build_payment_payload('transferencia');

        if ($document !== null) {
            SepaMandateService::set_requested_flag($user_id, true);
        }

        if ($document !== null) {
            $estado['documento_sepa_sin_firmar'] = $document;
            $estado['documento_sepa_firmado'] = null;
        }

        $gestion_sepa['datos_deudor'] = $debtor;
        $gestion_sepa['estado_documentos'] = $estado;
        $group['gestion_sepa'] = $gestion_sepa;

        update_field('gestion_pagos', $group, $scope);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persist_avatar_letters(int $user_id, array $data): void
    {
        if (! function_exists('update_field')) {
            return;
        }

        $scope    = 'user_' . $user_id;
        $initials = $this->build_initials($data);
        $palette  = $this->build_palette_from_email($data['email'] ?? '');

        if ($initials === '' && empty(array_filter($palette))) {
            return;
        }

        $payload = [
            'iniciales'                          => $initials,
            'fondo'                              => $palette['bg'] ?? '',
            'fondo_dark'                         => $palette['bg_dark'] ?? '',
            'texto'                              => $palette['text'] ?? '',
            'texto_dark'                         => $palette['text_dark'] ?? '',
            'mostrar_aunque_tenga_foto_de_perfil'=> 0,
        ];

        update_field('letras_avatar', $payload, $scope);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function build_initials(array $data): string
    {
        $role = isset($data['role']) ? (string) $data['role'] : '';

        if ($role === 'go_particular') {
            return $this->initials_from_personal(
                (string) ($data['first_name'] ?? ''),
                (string) ($data['last_name'] ?? '')
            );
        }

        $company = '';
        if (isset($data['company']['trade_name'])) {
            $company = (string) $data['company']['trade_name'];
        }

        return $this->initials_from_company($company);
    }

    private function initials_from_personal(string $first_name, string $last_name): string
    {
        $first = trim($first_name);
        $last  = trim($last_name);

        if ($first === '' && $last === '') {
            return '';
        }

        $initials = '';
        if ($first !== '') {
            $initials .= mb_strtoupper(mb_substr($first, 0, 1));
        }

        if ($last !== '') {
            $initials .= mb_strtoupper(mb_substr($last, 0, 1));
        } elseif (mb_strlen($first) > 1) {
            $initials .= mb_strtoupper(mb_substr($first, 1, 1));
        }

        return mb_substr($initials, 0, 2);
    }

    private function initials_from_company(string $name): string
    {
        $name  = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $parts = $name === '' ? [] : explode(' ', $name);

        if (empty($parts)) {
            return '';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $first  = $parts[0];
        $second = $parts[1] ?? '';

        if (mb_strlen($first) === 2) {
            return mb_strtoupper($first);
        }

        if ($second !== '' && preg_match('/^\d/', $second)) {
            return mb_strtoupper(mb_substr($first, 0, 2));
        }

        $initials = mb_strtoupper(mb_substr($first, 0, 1));
        if ($second !== '') {
            $initials .= mb_strtoupper(mb_substr($second, 0, 1));
        }

        return mb_substr($initials, 0, 2);
    }

    /**
     * @return array{bg:string,bg_dark:string,text:string,text_dark:string}
     */
    private function build_palette_from_email(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            return [
                'bg'       => '',
                'bg_dark'  => '',
                'text'     => '',
                'text_dark'=> '',
            ];
        }

        $hash = $this->string_to_hash($email);
        $hue  = abs($hash % 360);

        return [
            'bg'        => $this->hsl_to_rgb_string($hue, 65, 85),
            'text'      => $this->hsl_to_rgb_string($hue, 80, 25),
            'bg_dark'   => $this->hsl_to_rgb_string($hue, 50, 30),
            'text_dark' => $this->hsl_to_rgb_string($hue, 70, 90),
        ];
    }

    private function string_to_hash(string $value): int
    {
        $hash = 0;
        $len  = mb_strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $char  = mb_ord(mb_substr($value, $i, 1));
            $hash  = $char + (($hash << 5) - $hash);
            $hash |= 0;
        }

        return $hash;
    }

    private function hsl_to_rgb_string(int $h, int $s, int $l): string
    {
        $h = $h % 360;
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        $k = static function (int $n, float $h): float {
            return fmod($n + $h / 30, 12);
        };

        $a = $s * min($l, 1 - $l);

        $f = static function (int $n) use ($l, $a, $k, $h): float {
            $component = $l - $a * max(-1, min($k($n, $h) - 3, min(9 - $k($n, $h), 1)));
            return round(255 * $component);
        };

        $r = (int) $f(0);
        $g = (int) $f(8);
        $b = (int) $f(4);

        return sprintf('rgb(%d,%d,%d)', $r, $g, $b);
    }

    /**
     * @param array<string, mixed> $sepa
     */
    private function persist_sepa_meta(int $user_id, array $sepa): void
    {
        if (empty($sepa)) {
            return;
        }

        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_nombre_deudor', $sepa['name'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_direccion_deudor', $sepa['address'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_codigo_postal', $sepa['postal_code'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_poblacion', $sepa['city'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_provincia', $sepa['state'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_pais_deudor', $sepa['country'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_swift_bic', $sepa['swift'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_numero_cuenta', $sepa['iban'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_tipo_pago', $sepa['payment_type'] ?? '');

        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_codigo_postal', $sepa['postal_code'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_poblacion', $sepa['city'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_provincia', $sepa['state'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_numero_cuenta', $sepa['iban'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta', $sepa['iban'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_cp', $sepa['postal_code'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_ciudad', $sepa['city'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_region', $sepa['state'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_pais_deudor', $sepa['country'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_fecha_firma', $sepa['signature_date'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_localidad_firma', $sepa['signature_locality'] ?? '');
        update_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_tipo_pago', $sepa['payment_type'] ?? '');

        SepaMandateService::set_status($user_id, SepaMandateService::STATUS_PENDING_SIGNATURE);
        SepaMandateService::set_activation_flag($user_id, false, SepaMandateService::ACTIVATION_PENDING);
        SepaMandateService::set_payment_method($user_id, 'transferencia');
        SepaMandateService::set_requested_flag($user_id, true);
    }

    /**
     * @param array<string, mixed> $uploads
     */
    private function cleanup_uploads(array $uploads): void
    {
        foreach ($uploads as $file) {
            if (! is_array($file)) {
                continue;
            }
            if (! empty($file['id'])) {
                wp_delete_attachment((int) $file['id'], true);
            } elseif (! empty($file['file']) && file_exists($file['file'])) {
                unlink($file['file']);
            }
        }
    }

    /**
     * @return array{code: string, token: string, expires: int}|WP_Error
     */
    private function issue_verification(int $user_id, string $email)
    {
        $code   = $this->generate_code();
        $hash   = wp_hash_password($code);
        $token  = wp_generate_password(32, false);
        $expiry = time() + self::CODE_EXPIRATION;

        update_user_meta($user_id, RegistrationMeta::HASH, $hash);
        update_user_meta($user_id, RegistrationMeta::TOKEN, $token);
        update_user_meta($user_id, RegistrationMeta::EXPIRES, $expiry);
        update_user_meta($user_id, RegistrationMeta::ATTEMPTS, 0);
        update_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL, 0);
        update_user_meta($user_id, RegistrationMeta::RESEND_COUNT, 1);
        update_user_meta($user_id, RegistrationMeta::RESEND_LAST, time());
        update_user_meta($user_id, RegistrationMeta::RESEND_WINDOW, time());
        RegistrationMeta::mark_required($user_id, true);

        return [
            'code'   => $code,
            'token'  => $token,
            'expires'=> $expiry,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notify_admin(int $user_id, array $data, array $uploads): bool
    {
        if (! EmailReputationGuard::should_send_event('register_admin', ['user_id' => $user_id])) {
            return false;
        }

        $delivery = $this->resolve_admin_recipients();
        if (empty($delivery['to'])) {
            return false;
        }

        $profile_url = '';
        $user        = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            $slug = $user->user_nicename !== '' ? $user->user_nicename : $user->user_login;
            $slug = sanitize_title($slug);

            if ($slug !== '') {
                $profile_url = trailingslashit(home_url('/garantias-online/clientes/' . rawurlencode($slug)));
            }
        }

        $context = [
            'user' => [
                'name'   => $data['first_name'] . ' ' . $data['last_name'],
                'email'  => $data['email'],
                'phone'  => $data['phone'],
                'channel'=> $data['channel_label'],
                'profile_url' => $profile_url,
            ],
            'channel_key'   => $data['channel'],
            'channel_label' => $data['channel_label'],
            'requires_transfer' => $data['channel'] === 'individual',
            'company' => $data['company'],
            'workshop'=> $data['has_workshop'] ? $data['workshop'] : null,
            'web'     => $data['has_web'] ? $data['web_url'] : '',
            'sepa'    => $data['enable_sepa'] ? $data['sepa'] : null,
            'auto_signature' => $data['auto_signature'],
        ];

        $body = $this->renderer->render('register-admin', $context);
        $subject = sprintf(__('Nuevo registro: %s', 'garantias-online-360vo'), $data['company']['trade_name'] ?: $data['first_name']);

        $headers = [];
        $from_header = EmailSettings::buildFromHeader('admin');
        if ($from_header !== '') {
            $headers[] = $from_header;
        }

        $message = new EmailMessage(
            $delivery['to'],
            $subject,
            $body,
            $headers,
            [],
            [
                'reply_to' => $delivery['reply_to'],
            ]
        );

        return $this->mailer->send($message);
    }

    private function notify_welcome(int $user_id): bool
    {
        if (! EmailReputationGuard::should_send_event('register_welcome', ['user_id' => $user_id])) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return false;
        }

        $first_name = trim((string) get_user_meta($user_id, 'first_name', true));
        if ($first_name === '') {
            $first_name = $user->display_name !== '' ? $user->display_name : __('Profesional', 'garantias-online-360vo');
        }

        $channel_key = (string) get_user_meta($user_id, 'datos_empresa_tipo_profesional', true);
        $channel_label = (string) get_user_meta($user_id, 'datos_empresa_tipo_profesional_label', true);
        if ($channel_label === '' && isset(self::CHANNELS[$channel_key]['label'])) {
            $channel_label = self::CHANNELS[$channel_key]['label'];
        }
        if ($channel_label === '') {
            $channel_label = __('Profesional', 'garantias-online-360vo');
        }

        $company_name = (string) get_user_meta($user_id, 'datos_empresa_nombre_comercial', true);
        if ($company_name === '') {
            $company_name = (string) get_user_meta($user_id, 'datos_empresa_razon_social', true);
        }

        $pending_meta = SepaMandateService::get_document_meta($user_id, SepaMandateService::TYPE_PENDING);
        $has_pending_mandate = $pending_meta['hash'] !== '';
        $attachments = [];
        $temporary_files = [];

        if ($has_pending_mandate) {
            if (! function_exists('wp_tempnam')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $binary = SepaMandateService::retrieve_document($user_id, SepaMandateService::TYPE_PENDING);
            if (is_string($binary) && $binary !== '') {
                $filename = $pending_meta['filename'] !== '' ? $pending_meta['filename'] : 'mandato-sepa.pdf';
                $filename = sanitize_file_name($filename);
                if ($filename === '') {
                    $filename = 'mandato-sepa.pdf';
                }
                if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
                    $filename .= '.pdf';
                }

                $tmp = '';
                $temp_dir = trailingslashit(get_temp_dir());
                if ($temp_dir !== '' && is_dir($temp_dir) && is_writable($temp_dir)) {
                    $unique = wp_unique_filename($temp_dir, $filename);
                    if ($unique !== '') {
                        $tmp = $temp_dir . $unique;
                    }
                }

                if ($tmp === '') {
                    $tmp = wp_tempnam($filename);
                }

                if ($tmp) {
                    $written = file_put_contents($tmp, $binary);
                    if ($written !== false) {
                        $attachments[]   = $tmp;
                        $temporary_files[] = $tmp;
                    } else {
                        @unlink($tmp);
                    }
                }
            }
        }

        $context = [
            'name'          => $first_name,
            'company_name'  => $company_name,
            'channel_key'   => $channel_key,
            'channel_label' => $channel_label,
            'account_url'   => home_url('/garantias-online/'),
            'support_url'   => home_url('/garantias-online/soporte/'),
            'signature'     => $this->get_signature_html(),
            'sepa_pending'  => $has_pending_mandate,
        ];

        $body = $this->renderer->render('register-welcome', $context);
        if ($body === '') {
            return false;
        }

        $subject = __('Tu cuenta ya está activa en Garantías Online', 'garantias-online-360vo');
        $headers = [];
        $from_header = EmailSettings::buildFromHeader('professional');
        if ($from_header !== '') {
            $headers[] = $from_header;
        }

        $metadata = [];
        $reply_to = EmailSettings::getReplyTo();
        if ($reply_to !== '') {
            $metadata['reply_to'] = $reply_to;
        }

        $message = new EmailMessage([$user->user_email], $subject, $body, $headers, $attachments, $metadata);

        $sent = $this->mailer->send($message);

        foreach ($temporary_files as $file) {
            if (is_string($file) && file_exists($file)) {
                @unlink($file);
            }
        }

        return $sent;
    }

    /**
     * Limpia códigos caducados y ventanas de reenvío expiradas.
     *
     * @return array{tokens:int, windows:int}
     */
    public static function cleanup_stale_records(): array
    {
        $tokens_cleared = 0;
        $windows_reset  = 0;
        $now = time();

        do {
            $query = new \WP_User_Query([
                'fields'     => 'ids',
                'number'     => 50,
                'meta_query' => [
                    [
                        'key'     => RegistrationMeta::EXPIRES,
                        'value'   => $now,
                        'compare' => '<',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]);
            $ids = $query->get_results();
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $user_id) {
                $status = get_user_meta($user_id, RegistrationMeta::STATUS, true);
                if (is_string($status) && strtolower($status) === 'verified') {
                    continue;
                }

                delete_user_meta($user_id, RegistrationMeta::HASH);
                delete_user_meta($user_id, RegistrationMeta::EXPIRES);
                delete_user_meta($user_id, RegistrationMeta::ATTEMPTS);
                delete_user_meta($user_id, RegistrationMeta::LOCKED_UNTIL);
                delete_user_meta($user_id, RegistrationMeta::RESEND_LAST);
                $tokens_cleared++;
            }
        } while (count($ids) === 50);

        $threshold = $now - self::RESEND_WINDOW;
        if ($threshold > 0) {
            do {
                $query = new \WP_User_Query([
                    'fields'     => 'ids',
                    'number'     => 50,
                    'meta_query' => [
                        [
                            'key'     => RegistrationMeta::RESEND_WINDOW,
                            'value'   => $threshold,
                            'compare' => '<',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                ]);
                $ids = $query->get_results();
                if (empty($ids)) {
                    break;
                }

                foreach ($ids as $user_id) {
                    delete_user_meta($user_id, RegistrationMeta::RESEND_WINDOW);
                    delete_user_meta($user_id, RegistrationMeta::RESEND_COUNT);
                    $windows_reset++;
                }
            } while (count($ids) === 50);
        }

        if ($tokens_cleared > 0 || $windows_reset > 0) {
            ActivityLogger::log('user.verification_cleanup', [
                'event_category' => 'system',
                'context'        => [
                    'tokens'  => $tokens_cleared,
                    'windows' => $windows_reset,
                ],
            ]);
        }

        return [
            'tokens'  => $tokens_cleared,
            'windows' => $windows_reset,
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function notify_user(int $user_id, $data, string $code, int $expires): bool
    {
        if (! EmailReputationGuard::should_send_event('register_verification', ['user_id' => $user_id])) {
            return false;
        }

        $first_name = '';
        if (is_array($data) && isset($data['first_name'])) {
            $first_name = (string) $data['first_name'];
        } elseif ($user_id) {
            $first_name = get_user_meta($user_id, 'first_name', true);
        }
        $first_name = $first_name !== '' ? $first_name : __('Profesional', 'garantias-online-360vo');

        $email = is_array($data) && isset($data['email'])
            ? (string) $data['email']
            : (string) get_userdata($user_id)->user_email;

        $channel_key = '';
        $channel_label = '';
        if (is_array($data)) {
            if (isset($data['channel'])) {
                $channel_key = (string) $data['channel'];
            }
            if (isset($data['channel_label'])) {
                $channel_label = (string) $data['channel_label'];
            }
        }
        if ($channel_key === '' && $user_id) {
            $channel_key = (string) get_user_meta($user_id, 'datos_empresa_tipo_profesional', true);
        }
        if ($channel_label === '' && isset(self::CHANNELS[$channel_key]['label'])) {
            $channel_label = self::CHANNELS[$channel_key]['label'];
        }
        if ($channel_label === '') {
            $channel_label = __('Profesional', 'garantias-online-360vo');
        }

        $context = [
            'name'        => $first_name,
            'code'        => $code,
            'expires_at'  => gmdate('c', $expires),
            'expires_in'  => max(0, $expires - time()),
            'signature'   => $this->get_signature_html(),
            'channel_key'   => $channel_key,
            'channel_label' => $channel_label,
        ];

        $body = $this->renderer->render('register-verification', $context);
        $subject = __('Verifica tu cuenta en Garantías Online', 'garantias-online-360vo');

        $headers = [];
        $from_header = EmailSettings::buildFromHeader('professional');
        if ($from_header !== '') {
            $headers[] = $from_header;
        }

        $metadata = [];
        $reply_to = EmailSettings::getReplyTo();
        if ($reply_to !== '') {
            $metadata['reply_to'] = $reply_to;
        }

        $message = new EmailMessage([
            $email,
        ], $subject, $body, $headers, [], $metadata);

        return $this->mailer->send($message);
    }

    private function get_signature_html(): string
    {
        static $signature;

        if ($signature === null) {
            $signature = EmailSettings::getSignature();
        }

        return $signature;
    }

    /**
     * @return array{to: array<int, string>, bcc: array<int, string>, reply_to: string}
     */
    private function resolve_admin_recipients(): array
    {
        $to      = [];
        $bcc     = [];
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

                    $email_field = $row['admin_recipients'] ?? ($row['correo'] ?? '');
                    $email = sanitize_email($email_field);
                    if (! is_email($email)) {
                        continue;
                    }

                    $is_bcc = false;
                    if (array_key_exists('copia_oculta', $row)) {
                        $is_bcc = (bool) $row['copia_oculta'];
                    } else {
                        $type = sanitize_key($row['destino'] ?? '');
                        $is_bcc = $type === 'bcc';
                    }

                    if ($is_bcc) {
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

        return [
            'to'       => array_values(array_unique(array_filter($to))),
            'bcc'      => array_values(array_unique(array_filter($bcc))),
            'reply_to' => $reply_to,
        ];
    }

    private function generate_code(): string
    {
        $min = (int) pow(10, self::CODE_LENGTH - 1);
        $max = (int) pow(10, self::CODE_LENGTH) - 1;
        return (string) random_int($min, $max);
    }

    private function generate_username(string $first_name, string $last_name, string $trade_name, string $email): string
    {
        $base = $trade_name !== '' ? $trade_name . ' ' . $first_name : $first_name . ' ' . $last_name;
        if ($base === '') {
            $base = strstr($email, '@', true) ?: 'usuario';
        }

        $base = preg_replace('/\s+/', '_', strtolower(remove_accents($base)));
        $base = preg_replace('/[^a-z0-9_]/', '', $base);
        if ($base === '') {
            $base = 'usuario';
        }
        $base = substr($base, 0, 50);

        $candidate = $base;
        $index = 1;
        while (username_exists($candidate)) {
            $candidate = $base . '_' . $index;
            $index++;
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array<string, mixed>|WP_Error
     */
    private function store_image(?array $file)
    {
        if (! is_array($file) || empty($file['tmp_name'])) {
            return new WP_Error('go_register_upload', __('No se ha podido procesar la imagen subida.', 'garantias-online-360vo'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = ['test_form' => false];
        $handled = wp_handle_upload($file, $overrides);
        if (! is_array($handled) || isset($handled['error'])) {
            return new WP_Error('go_register_upload', $handled['error'] ?? __('Error al subir la imagen.', 'garantias-online-360vo'));
        }

        $attachment = [
            'post_mime_type' => $handled['type'],
            'post_title'     => sanitize_file_name($file['name'] ?? 'upload'),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $handled['file']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $handled['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return [
            'id'   => $attachment_id,
            'file' => $handled['file'],
            'url'  => $handled['url'],
        ];
    }

    private function sanitize_name($value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim(wp_strip_all_tags($value));
        return mb_substr($value, 0, 80);
    }

    private function sanitize_phone($value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (! preg_match('/^[6-9]\d{8}$/', (string) $digits)) {
            return '';
        }
        return $digits;
    }

    private function is_weak_password(string $password): bool
    {
        if (strlen($password) < 8) {
            return true;
        }
        if (! preg_match('/\d/', $password)) {
            return true;
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return true;
        }
        return false;
    }

    private function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['1', 'true', 'on', 'yes'], true);
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        return false;
    }

    private function sanitize_company(string $channel, array $data)
    {
        $company = [
            'trade_name' => '',
            'legal_name' => '',
            'tax_id'     => '',
            'address'    => [
                'street'      => '',
                'postal_code' => '',
                'city'        => '',
                'province'    => '',
                'country'     => '',
            ],
        ];

        $requires_company = in_array($channel, ['compraventa', 'concesionario'], true);
        if (! $requires_company) {
            return $company;
        }

        $trade = $this->sanitize_text($data['company_trade_name'] ?? '');
        $legal = $this->sanitize_text($data['company_legal_name'] ?? '');
        $cif   = $this->sanitize_text($data['company_cif'] ?? '');
        $street = $this->sanitize_text($data['company_address'] ?? '');
        $postal = $this->sanitize_text($data['company_postal_code'] ?? '');
        $city   = $this->sanitize_text($data['company_city'] ?? '');
        $province = $this->sanitize_text($data['company_province'] ?? '');

        if ($trade === '' || $cif === '' || $street === '' || $postal === '' || $city === '' || $province === '') {
            return new WP_Error('go_register_company', __('Completa los datos de la empresa.', 'garantias-online-360vo'));
        }

        if (! preg_match('/^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/', $postal)) {
            return new WP_Error('go_register_company_postal', __('Introduce un código postal válido.', 'garantias-online-360vo'));
        }

        $company['trade_name'] = $trade;
        $company['legal_name'] = $legal;
        $company['tax_id']     = strtoupper($cif);
        $company['address'] = [
            'street'      => $street,
            'postal_code' => $postal,
            'city'        => $city,
            'province'    => $province,
            'country'     => 'España',
        ];

        return $company;
    }

    private function sanitize_workshop(bool $enabled, array $data)
    {
        if (! $enabled) {
            return [];
        }
        $name        = $this->sanitize_text($data['workshop_name'] ?? '');
        $address     = $this->sanitize_text($data['workshop_address'] ?? '');
        $fiscal_name = $this->sanitize_text($data['workshop_fiscal_name'] ?? '');
        $tax_id      = strtoupper($this->sanitize_text($data['workshop_tax_id'] ?? ''));
        $contact     = $this->sanitize_text($data['workshop_contact'] ?? '');
        $phone       = $this->sanitize_phone($data['workshop_phone'] ?? '');
        $email       = sanitize_email($data['workshop_email'] ?? '');

        if ($name === '' || $address === '' || $fiscal_name === '' || $tax_id === '' || $contact === '' || $phone === '' || ! is_email($email)) {
            return new WP_Error('go_register_workshop', __('Completa los datos del taller para continuar.', 'garantias-online-360vo'));
        }

        return [
            'name'        => $name,
            'address'     => $address,
            'fiscal_name' => $fiscal_name,
            'tax_id'      => $tax_id,
            'contact'     => $contact,
            'phone'       => $phone,
            'email'       => $email,
        ];
    }

    private function sanitize_sepa(bool $enabled, array $data, string $reference, string $generated_at)
    {
        if (! $enabled) {
            return [];
        }

        $name    = $this->sanitize_text($data['sepa_name'] ?? '');
        $address = $this->sanitize_text($data['sepa_address'] ?? '');
        $postal  = $this->sanitize_text($data['sepa_postal_code'] ?? '');
        $city    = $this->sanitize_text($data['sepa_city'] ?? '');
        $state   = $this->sanitize_text($data['sepa_state'] ?? '');
        $country = $this->sanitize_text($data['sepa_country'] ?? 'España');
        $swift   = strtoupper($this->sanitize_text($data['sepa_swift'] ?? ''));
        $iban    = strtoupper(str_replace(' ', '', (string) ($data['sepa_iban'] ?? '')));

        if (in_array('', [$name, $address, $postal, $city, $state, $swift, $iban], true)) {
            return new WP_Error('go_register_sepa', __('Completa todos los datos SEPA para continuar.', 'garantias-online-360vo'));
        }

        if (! preg_match('/^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/', $postal)) {
            return new WP_Error('go_register_sepa_postal', __('Introduce un código postal válido.', 'garantias-online-360vo'));
        }
        if (! preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $swift)) {
            return new WP_Error('go_register_sepa_swift', __('Introduce un código SWIFT/BIC válido.', 'garantias-online-360vo'));
        }
        if (! $this->is_valid_iban($iban)) {
            return new WP_Error('go_register_sepa_iban', __('Introduce un IBAN válido.', 'garantias-online-360vo'));
        }

        $creditor = SepaMandateService::get_creditor_data();
        $payment_type = isset($creditor['payment_type']) ? sanitize_key($creditor['payment_type']) : 'recurrente';
        if ($payment_type === '') {
            $payment_type = 'recurrente';
        }

        $payment_label = '';
        if (! empty($creditor['payment_type_label'])) {
            $payment_label = sanitize_text_field((string) $creditor['payment_type_label']);
        }
        if ($payment_label === '') {
            $payment_label = $payment_type === 'unico'
                ? __('Único', 'garantias-online-360vo')
                : __('Recurrente', 'garantias-online-360vo');
        }

        $creditor_province = isset($creditor['province']) ? $this->sanitize_text($creditor['province']) : '';
        $signature_locality = $creditor_province !== '' ? $creditor_province : $state;
        $signature_date_display = wp_date('d/m/Y');
        $signature_iso = wp_date(DATE_ATOM);
        $resolved_generated_at = $generated_at !== '' ? $generated_at : $signature_iso;

        return [
            'name'        => $name,
            'address'     => $address,
            'postal_code' => $postal,
            'city'        => $city,
            'state'       => $state,
            'country'     => $country !== '' ? $country : ($creditor['country'] ?? 'España'),
            'swift'       => $swift,
            'iban'        => $this->format_iban($iban),
            'payment_type'=> $payment_type,
            'payment_type_label' => $payment_label,
            'reference'   => $reference,
            'generated_at'=> $resolved_generated_at,
            'signature_date' => $signature_date_display,
            'signature_iso'  => $signature_iso,
            'signature_locality' => $signature_locality,
        ];
    }

    private function validate_image_file(array $file, string $key)
    {
        if (! isset($file['tmp_name']) || ! file_exists($file['tmp_name'])) {
            return new WP_Error('go_register_upload', __('No se ha podido leer el archivo subido.', 'garantias-online-360vo'));
        }
        $size = isset($file['size']) ? (int) $file['size'] : filesize($file['tmp_name']);
        if ($size > self::MAX_IMAGE_SIZE) {
            return new WP_Error('go_register_upload_size', __('La imagen supera el tamaño máximo permitido (5MB).', 'garantias-online-360vo'));
        }
        $type = wp_check_filetype($file['name'] ?? '', ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif']);
        if (empty($type['type'])) {
            return new WP_Error('go_register_upload_type', __('Formato de imagen no permitido.', 'garantias-online-360vo'));
        }
        return true;
    }

    private function validate_pdf_file(array $file)
    {
        if (! isset($file['tmp_name']) || ! file_exists($file['tmp_name'])) {
            return new WP_Error('go_register_sepa_upload', __('No se ha podido leer el mandato SEPA generado.', 'garantias-online-360vo'));
        }

        $size = isset($file['size']) ? (int) $file['size'] : filesize($file['tmp_name']);
        if ($size > self::MAX_SEPA_SIZE) {
            return new WP_Error('go_register_sepa_size', __('El mandato SEPA supera el tamaño máximo permitido (5MB).', 'garantias-online-360vo'));
        }

        $type = wp_check_filetype($file['name'] ?? '', ['pdf' => 'application/pdf']);
        if (empty($type['type']) || $type['type'] !== 'application/pdf') {
            return new WP_Error('go_register_sepa_type', __('El mandato SEPA debe ser un archivo PDF válido.', 'garantias-online-360vo'));
        }

        return true;
    }

    private function extract_file(array $files, string $key): ?array
    {
        if (! isset($files[$key])) {
            return null;
        }
        $file = $files[$key];
        if (! is_array($file)) {
            return null;
        }
        if (isset($file['error']) && (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    private function sanitize_text($value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim(wp_strip_all_tags($value));
        return mb_substr($value, 0, 120);
    }

    private function is_valid_url(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        $parsed = wp_parse_url($value);
        if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }
        return in_array($parsed['scheme'], ['http', 'https'], true);
    }

    private function is_valid_iban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (! preg_match('/^[A-Z0-9]{15,34}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;

        for ($i = 0, $len = strlen($rearranged); $i < $len; $i++) {
            $char = $rearranged[$i];
            $value = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            for ($j = 0, $vlen = strlen($value); $j < $vlen; $j++) {
                $digit = (int) $value[$j];
                $remainder = ($remainder * 10 + $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    private function format_iban(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }

    private function detect_ip(): string
    {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (! empty($_SERVER[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $value = explode(',', (string) $_SERVER[$key]);
                $ip = trim($value[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
