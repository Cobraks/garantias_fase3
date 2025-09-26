<?php

namespace GarantiasOnline360VO\Register;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\Notifications\Email\EmailMessage;
use GarantiasOnline360VO\Notifications\Email\Mailer;
use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use GarantiasOnline360VO\SettingsPage;
use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class RegistrationService
{
    private const CODE_EXPIRATION     = DAY_IN_SECONDS;
    private const CODE_LENGTH         = 6;
    private const MAX_ATTEMPTS        = 5;
    private const LOCK_DURATION       = 5 * MINUTE_IN_SECONDS;
    private const RESEND_LIMIT        = 3;
    private const RESEND_WINDOW       = HOUR_IN_SECONDS;
    private const RESEND_COOLDOWN     = 60;
    private const MAX_IMAGE_SIZE      = 5_242_880; // 5 MB

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

            $verification = $this->issue_verification($user_id, $sanitized['email']);
            if (is_wp_error($verification)) {
                $this->cleanup_uploads($uploaded);
                wp_delete_user($user_id);
                return $verification;
            }

            $admin_result = $this->notify_admin($user_id, $sanitized, $uploaded);

            $user_result  = $this->notify_user($user_id, $sanitized, $verification['code'], $verification['expires']);
            if (! $user_result) {
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

        $sepa = $this->sanitize_sepa($enable_sepa, $data);
        if ($sepa instanceof WP_Error) {
            return $sepa;
        }

        $avatar    = $this->extract_file($files, 'avatar');
        $signature = $this->extract_file($files, 'signature');
        $stamp     = $this->extract_file($files, 'stamp');

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
                $sepa = $data['sepa'];
                $address_line = trim(sprintf('%s %s %s', $sepa['postal_code'], $sepa['city'], $sepa['state']));
                $sepa_group = [
                    'gestion_sepa' => [
                        'datos_deudor' => [
                            'nombre_deudor'         => $sepa['name'],
                            'direccion_deudor'      => $sepa['address'],
                            'cp_poblacion_provincia'=> $address_line,
                            'pais_deudor'           => $sepa['country'],
                            'swift_bic'             => $sepa['swift'],
                            'numero_cienta'         => $sepa['iban'],
                            'tipo_pago'             => [
                                'value' => 'recurrente',
                                'label' => 'Recurrente',
                            ],
                        ],
                    ],
                ];
                update_field('gestion_pagos', $sepa_group, $scope);
            }

            if (! empty($uploads['avatar']['id'])) {
                update_field('profile_image', $uploads['avatar']['id'], $scope);
            }
        }

        update_user_meta($user_id, 'datos_empresa_tipo_profesional', $data['channel']);
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
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_nombre_deudor', $sepa['name']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_direccion_deudor', $sepa['address']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_codigo_postal', $sepa['postal_code']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_poblacion', $sepa['city']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_provincia', $sepa['state']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_swift_bic', $sepa['swift']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_numero_cuenta', $sepa['iban']);
            update_user_meta($user_id, 'gestion_pagos_gestion_sepa_tipo_pago', 'recurrente');
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
        $delivery = $this->resolve_admin_recipients();
        if (empty($delivery['to']) && empty($delivery['bcc'])) {
            return false;
        }

        $context = [
            'user' => [
                'name'   => $data['first_name'] . ' ' . $data['last_name'],
                'email'  => $data['email'],
                'phone'  => $data['phone'],
                'channel'=> $data['channel_label'],
            ],
            'company' => $data['company'],
            'workshop'=> $data['has_workshop'] ? $data['workshop'] : null,
            'web'     => $data['has_web'] ? $data['web_url'] : '',
            'sepa'    => $data['enable_sepa'] ? $data['sepa'] : null,
            'auto_signature' => $data['auto_signature'],
        ];

        $body = $this->renderer->render('register-admin', $context);
        $subject = sprintf(__('Nuevo registro: %s', 'garantias-online-360vo'), $data['company']['trade_name'] ?: $data['first_name']);

        $message = new EmailMessage(
            $delivery['to'] ?: [$delivery['primary']],
            $subject,
            $body,
            [],
            [],
            [
                'bcc'      => $delivery['bcc'],
                'reply_to' => $delivery['reply_to'],
            ]
        );

        return $this->mailer->send($message);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function notify_user(int $user_id, $data, string $code, int $expires): bool
    {
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

        $context = [
            'name'        => $first_name,
            'code'        => $code,
            'expires_at'  => gmdate('c', $expires),
            'expires_in'  => max(0, $expires - time()),
            'verification_url' => home_url('/garantias-online/registro/'),
        ];

        $body = $this->renderer->render('register-verification', $context);
        $subject = __('Verifica tu cuenta en Garantías Online', 'garantias-online-360vo');

        $message = new EmailMessage([
            $email,
        ], $subject, $body);

        return $this->mailer->send($message);
    }

    /**
     * @return array{primary: string, to: array<int, string>, bcc: array<int, string>, reply_to: string}
     */
    private function resolve_admin_recipients(): array
    {
        $primary = get_option('admin_email');
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

        $primary = is_email($primary) ? $primary : '';
        if (empty($to) && $primary !== '') {
            $to[] = $primary;
        }

        return [
            'primary'  => $primary,
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
        $name    = $this->sanitize_text($data['workshop_name'] ?? '');
        $address = $this->sanitize_text($data['workshop_address'] ?? '');
        $contact = $this->sanitize_text($data['workshop_contact'] ?? '');
        $phone   = $this->sanitize_phone($data['workshop_phone'] ?? '');
        $email   = sanitize_email($data['workshop_email'] ?? '');

        if ($name === '' || $address === '' || $contact === '' || $phone === '' || ! is_email($email)) {
            return new WP_Error('go_register_workshop', __('Completa los datos del taller para continuar.', 'garantias-online-360vo'));
        }

        return [
            'name'    => $name,
            'address' => $address,
            'contact' => $contact,
            'phone'   => $phone,
            'email'   => $email,
        ];
    }

    private function sanitize_sepa(bool $enabled, array $data)
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

        return [
            'name'        => $name,
            'address'     => $address,
            'postal_code' => $postal,
            'city'        => $city,
            'state'       => $state,
            'country'     => $country,
            'swift'       => $swift,
            'iban'        => $this->format_iban($iban),
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
