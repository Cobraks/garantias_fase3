<?php

namespace GarantiasOnline360VO\Notifications\Push;

use GarantiasOnline360VO\Support\UserProfileResolver;
use GarantiasOnline360VO\Svg;
use function home_url;

if (! defined('ABSPATH')) {
    exit;
}

class PushMessageFactory
{
    /**
     * @param array<string, mixed> $activity_record
     * @return array<string, mixed>|null
     */
    public function build_from_activity(string $event_type, array $activity_record): ?array
    {
        switch ($event_type) {
            case 'auth.login_success':
                return $this->build_login_success($activity_record);
            case 'auth.logout':
                return $this->build_logout($activity_record);
            case 'user.verification_verified':
                return $this->build_user_verified($activity_record);
            case 'guarantee.created':
                return $this->build_guarantee_created($activity_record);
            case 'payment.reported':
                return $this->build_transfer_reported($activity_record);
            case 'sepa.signed_uploaded':
                return $this->build_sepa_uploaded($activity_record);
            default:
                return null;
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_login_success(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $actor_name = isset($record['actor_name']) ? sanitize_text_field((string) $record['actor_name']) : '';
        $username = isset($context['username']) ? sanitize_text_field((string) $context['username']) : '';
        $actor_email = isset($record['actor_email']) ? sanitize_email((string) $record['actor_email']) : '';
        $actor_label = $actor_name !== '' ? $actor_name : ($actor_email !== '' ? $actor_email : $username);
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;

        $link = $actor_id > 0
            ? admin_url('user-edit.php?user_id=' . $actor_id)
            : admin_url('users.php');

        return [
            'title' => __('Inicio de sesión registrado', 'garantias-online-360vo'),
            'body'  => $actor_label !== ''
                ? sprintf(__('El usuario %s ha iniciado sesión.', 'garantias-online-360vo'), $actor_label)
                : __('Se ha registrado un nuevo inicio de sesión.', 'garantias-online-360vo'),
            'link'  => $link,
            'icon'      => Svg::data_uri('login'),
            'icon_slug' => 'login',
            'tone'      => 'info',
            'meta'      => $actor_label !== ''
                ? [$this->meta_entry(__('Usuario', 'garantias-online-360vo'), $actor_label)]
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_logout(array $record): array
    {
        $actor_name = isset($record['actor_name']) ? sanitize_text_field((string) $record['actor_name']) : '';
        $actor_email = isset($record['actor_email']) ? sanitize_email((string) $record['actor_email']) : '';
        $actor_label = $actor_name !== '' ? $actor_name : $actor_email;
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;

        $link = $actor_id > 0
            ? admin_url('user-edit.php?user_id=' . $actor_id)
            : admin_url('users.php');

        return [
            'title' => __('Sesión cerrada', 'garantias-online-360vo'),
            'body'  => $actor_label !== ''
                ? sprintf(__('El usuario %s ha cerrado la sesión.', 'garantias-online-360vo'), $actor_label)
                : __('Se ha cerrado una sesión de usuario.', 'garantias-online-360vo'),
            'link'  => $link,
            'icon'      => Svg::data_uri('logout'),
            'icon_slug' => 'logout',
            'tone'      => 'info',
            'meta'      => $actor_label !== ''
                ? [$this->meta_entry(__('Usuario', 'garantias-online-360vo'), $actor_label)]
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_user_verified(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $email = isset($context['user_email']) ? sanitize_text_field((string) $context['user_email']) : '';
        $user_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;
        $channel_label = isset($context['channel_label']) ? sanitize_text_field((string) $context['channel_label']) : '';

        $meta = [];
        if ($channel_label !== '') {
            $meta[] = $this->meta_entry(__('Canal', 'garantias-online-360vo'), $channel_label);
        }

        return [
            'title' => __('Nuevo usuario verificado', 'garantias-online-360vo'),
            'body'  => $email !== ''
                ? sprintf(__('El usuario %s ha verificado su cuenta.', 'garantias-online-360vo'), $email)
                : __('Se ha verificado una nueva cuenta de usuario.', 'garantias-online-360vo'),
            'link'  => $user_id > 0 ? admin_url('user-edit.php?user_id=' . $user_id) : admin_url('users.php'),
            'icon'      => Svg::data_uri('check_shield'),
            'icon_slug' => 'check_shield',
            'tone'      => 'success',
            'badge'     => __('Nuevo', 'garantias-online-360vo'),
            'meta'      => $meta,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_guarantee_created(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $initiator = isset($context['initiator_label']) ? (string) $context['initiator_label'] : '';
        $vendor_id = isset($context['vendor_id']) ? (int) $context['vendor_id'] : 0;
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;

        if ($vendor_id <= 0 && $guarantee_id > 0) {
            $stored_vendor = get_post_meta($guarantee_id, 'garantia_contratada_concesionario_empresa_profesional', true);
            if (is_numeric($stored_vendor)) {
                $vendor_id = (int) $stored_vendor;
            }
        }

        $plan_label = $this->resolve_plan_label($guarantee_id, $context);
        $status_label = $this->resolve_status_from_context($context, $guarantee_id);

        $company_label = $this->resolve_company_label($vendor_id, $context, $initiator);
        $plan_clean   = $this->sanitize_plain_text($plan_label);

        $company_display = $company_label !== ''
            ? $company_label
            : __('Un profesional', 'garantias-online-360vo');

        $plan_display = $plan_clean !== ''
            ? $plan_clean
            : __('sin identificar', 'garantias-online-360vo');

        $body = esc_html(sprintf(
            __('%1$s ha contratado una Cobertura %2$s', 'garantias-online-360vo'),
            $company_display,
            $plan_display
        ));

        $link = $this->build_guarantee_link($guarantee_id, $context);

        $meta = [];
        if ($status_label !== '') {
            $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');
        }

        if ($actor_id > 0 && $vendor_id > 0 && $actor_id !== $vendor_id) {
            $actor_label = UserProfileResolver::get_personal_name($actor_id);
            if ($actor_label === '' && $initiator !== '') {
                $actor_label = wp_strip_all_tags($initiator);
            }
            if ($actor_label !== '') {
                $meta[] = $this->meta_entry(__('Iniciada por', 'garantias-online-360vo'), $actor_label, 'actor');
            }
        }

        return [
            'title' => __('Nueva garantía', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('new_shield'),
            'icon_slug' => 'new_shield',
            'tone'      => 'primary',
            'badge'     => __('Nuevo', 'garantias-online-360vo'),
            'meta'      => $meta,
            'actions' => [
                [
                    'action' => 'view',
                    'title'  => __('Ver garantía', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_transfer_reported(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $amount = isset($context['payment_amount']) ? (string) $context['payment_amount'] : '';
        $link = $guarantee_id > 0
            ? get_permalink($guarantee_id)
            : admin_url('edit.php?post_type=garantia');

        $body = $title !== ''
            ? sprintf(__('Tenemos un justificante de pago para la garantía %s.', 'garantias-online-360vo'), $title)
            : __('Tenemos un nuevo justificante de pago pendiente de validar.', 'garantias-online-360vo');

        $meta = [];
        if ($amount !== '') {
            $meta[] = $this->meta_entry(__('Importe', 'garantias-online-360vo'), $amount);
        }

        if (! empty($context['payment_method_label'])) {
            $meta[] = $this->meta_entry(__('Método', 'garantias-online-360vo'), (string) $context['payment_method_label']);
        }

        $status_label = $this->resolve_status_from_context($context, $guarantee_id);
        if ($status_label !== '') {
            $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');
        }

        return [
            'title' => __('Transferencia confirmada', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('sell'),
            'icon_slug' => 'sell',
            'tone'      => 'warning',
            'meta'      => $meta,
            'actions' => [
                [
                    'action' => 'open-guarantee',
                    'title'  => __('Revisar garantía', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_sepa_uploaded(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;
        $vendor_id = isset($context['vendor_id']) ? (int) $context['vendor_id'] : 0;
        $link = $guarantee_id > 0
            ? get_permalink($guarantee_id)
            : admin_url('edit.php?post_type=garantia');

        $body = $title !== ''
            ? sprintf(__('Se ha subido el SEPA firmado para %s.', 'garantias-online-360vo'), $title)
            : __('Se ha subido un nuevo SEPA firmado.', 'garantias-online-360vo');

        $meta = [];
        $status_label = $this->resolve_status_from_context($context, $guarantee_id);
        if ($status_label !== '') {
            $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');
        }

        if ($actor_id > 0 && $vendor_id > 0 && $actor_id !== $vendor_id) {
            $actor_label = UserProfileResolver::get_personal_name($actor_id);
            if ($actor_label !== '') {
                $meta[] = $this->meta_entry(__('Subido por', 'garantias-online-360vo'), $actor_label, 'actor');
            }
        }

        return [
            'title' => __('SEPA pendiente de verificación', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('iban'),
            'icon_slug' => 'iban',
            'tone'      => 'warning',
            'meta'      => $meta,
            'actions' => [
                [
                    'action' => 'review-sepa',
                    'title'  => __('Revisar documentación', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_context($context): array
    {
        if (is_array($context)) {
            return $context;
        }

        if (is_string($context) && $context !== '') {
            $decoded = json_decode($context, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function resolve_plan_label(int $guarantee_id, array $context): string
    {
        $candidates = [];

        foreach (['plan_label', 'plan_name', 'plan_title', 'plan_display_name'] as $key) {
            if (! empty($context[$key]) && is_string($context[$key])) {
                $candidates[] = $this->sanitize_plain_text($context[$key]);
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if ($guarantee_id <= 0) {
            return '';
        }

        $plan_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_garantia', true);
        if ($plan_id <= 0) {
            return '';
        }

        $custom_plan = function_exists('get_field')
            ? get_field('detalles_modalidad_nombre_mostrar', $plan_id)
            : '';

        $label = is_string($custom_plan) && $custom_plan !== '' ? $custom_plan : get_the_title($plan_id);

        return is_string($label) ? wp_strip_all_tags($label) : '';
    }

    private function resolve_status_from_context(array $context, int $guarantee_id): string
    {
        if (! empty($context['current_state_label'])) {
            return wp_strip_all_tags((string) $context['current_state_label']);
        }

        $status = isset($context['current_state']) ? sanitize_key((string) $context['current_state']) : '';
        $label = $this->status_label($status);
        if ($label !== '') {
            return $label;
        }

        if ($guarantee_id > 0) {
            $stored = (string) get_post_meta($guarantee_id, 'estado_garantia_estado_contratacion', true);
            $label = $this->status_label($stored);
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function build_guarantee_link(int $guarantee_id, array $context): string
    {
        $base = home_url('/garantias-online/mis-garantias/');

        $plate = '';
        if (! empty($context['vehicle_plate'])) {
            $plate = (string) $context['vehicle_plate'];
        } elseif ($guarantee_id > 0) {
            $stored_plate = get_post_meta($guarantee_id, 'datos_vehiculo_matricula', true);
            if (is_string($stored_plate)) {
                $plate = $stored_plate;
            }
        }

        $plate = strtoupper(trim((string) $plate));
        if ($plate !== '') {
            $normalized = preg_replace('/[^A-Z0-9]/', '', $plate);
            if (is_string($normalized) && $normalized !== '') {
                $separator = strpos($base, '?') === false ? '?' : '&';

                return $base . $separator . 'matricula=' . rawurlencode($normalized);
            }
        }

        return $base;
    }

    private function resolve_company_label(int $vendor_id, array $context, string $initiator): string
    {
        $company = '';

        if ($vendor_id > 0) {
            $labels = UserProfileResolver::get_vendor_labels($vendor_id);
            if (! empty($labels['company']['trade_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['company']['trade_name']);
            }
            if ($company === '' && ! empty($labels['company_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['company_name']);
            }
            if ($company === '' && ! empty($labels['company']['legal_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['company']['legal_name']);
            }
            if ($company === '' && ! empty($labels['personal_full_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['personal_full_name']);
            }
            if ($company === '' && ! empty($labels['personal_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['personal_name']);
            }
        }

        if ($company === '' && ! empty($context['vendor_company_name'])) {
            $company = $this->sanitize_plain_text((string) $context['vendor_company_name']);
        }

        if ($company === '' && ! empty($context['vendor_name'])) {
            $company = $this->sanitize_plain_text((string) $context['vendor_name']);
        }

        if ($company === '' && ! empty($context['vendor_person_name'])) {
            $company = $this->sanitize_plain_text((string) $context['vendor_person_name']);
        }

        if ($company === '' && $initiator !== '') {
            $company = $this->sanitize_plain_text($initiator);
        }

        return $company;
    }

    private function sanitize_plain_text($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = wp_strip_all_tags($value);

        return trim($value);
    }

    private function status_label(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === '') {
            return '';
        }

        $map = [
            'new'                  => __('Nuevo', 'garantias-online-360vo'),
            'draft'                => __('Borrador', 'garantias-online-360vo'),
            'pending'              => __('Pendiente', 'garantias-online-360vo'),
            'pending_payment'      => __('Pendiente de pago', 'garantias-online-360vo'),
            'pending_cobro'        => __('Pend. Domiciliación', 'garantias-online-360vo'),
            'pendiente_pago'       => __('Pendiente de pago', 'garantias-online-360vo'),
            'validacion_pendiente' => __('Validación pendiente', 'garantias-online-360vo'),
            'pendiente_cobro'      => __('Pend. Domiciliación', 'garantias-online-360vo'),
            'sin_finalizar'        => __('Sin finalizar', 'garantias-online-360vo'),
            'publish'              => __('Publicada', 'garantias-online-360vo'),
            'publicada'            => __('Publicada', 'garantias-online-360vo'),
            'activada'             => __('Activada', 'garantias-online-360vo'),
            'activated'            => __('Activada', 'garantias-online-360vo'),
            'active'               => __('Activa', 'garantias-online-360vo'),
            'completed'            => __('Completada', 'garantias-online-360vo'),
            'cancelled'            => __('Cancelada', 'garantias-online-360vo'),
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        $status = str_replace(['_', '-'], ' ', $status);

        return $status !== '' ? ucfirst($status) : '';
    }

    private function meta_entry(string $label, string $text, string $type = ''): array
    {
        $entry = [
            'label' => $label,
            'text'  => $text,
        ];

        if ($type !== '') {
            $entry['type'] = $type;
        }

        return $entry;
    }
}
