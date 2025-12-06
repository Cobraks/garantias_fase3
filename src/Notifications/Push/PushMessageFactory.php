<?php

namespace GarantiasOnline360VO\Notifications\Push;

use GarantiasOnline360VO\Support\UserProfileResolver;
use GarantiasOnline360VO\Svg;
use WP_User;
use function esc_html;
use function home_url;
use function sanitize_title;
use function trailingslashit;

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
            case 'guarantee.contracted':
                return $this->build_guarantee_event($activity_record, $event_type);
            case 'guarantee.cancelled':
                return $this->build_guarantee_cancelled($activity_record);
            case 'guarantee.note_added':
                return $this->build_guarantee_note_added($activity_record);
            case 'payment.recorded':
                return $this->build_payment_recorded($activity_record);
            case 'payment.reported':
                return $this->build_transfer_reported($activity_record);
            case 'sepa.pending_requested':
                return $this->build_sepa_pending($activity_record);
            case 'sepa.signed_uploaded':
                return $this->build_sepa_uploaded($activity_record);
            case 'sepa.activated':
                return $this->build_sepa_activated($activity_record);
            case 'client.deleted':
                return $this->build_client_deleted($activity_record);
            case 'client.commercials_updated':
                return $this->build_client_commercials_updated($activity_record);
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
            'title' => __('Inicio de sesión', 'garantias-online-360vo'),
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
        $profile_url = isset($context['profile_url']) ? esc_url_raw((string) $context['profile_url']) : '';
        if ($profile_url === '' && $user_id > 0) {
            $profile_url = $this->build_client_profile_url($user_id);
        }

        $company_label = '';
        if ($user_id > 0) {
            $company_label = $this->resolve_company_label($user_id, ['vendor_id' => $user_id], '');
        }
        if ($company_label === '' && $email !== '') {
            $company_label = $email;
        }
        if ($company_label === '') {
            $company_label = __('Profesional', 'garantias-online-360vo');
        }

        $meta = [];
        if ($channel_label !== '') {
            $meta[] = $this->meta_entry(__('Canal', 'garantias-online-360vo'), $channel_label);
        }
        if ($email !== '') {
            $meta[] = $this->meta_entry(__('Correo', 'garantias-online-360vo'), $email, 'actor');
        }

        $body = sprintf(
            /* translators: %s: company or professional name */
            __('%s se ha registrado en Garantías Online.', 'garantias-online-360vo'),
            '<strong>' . esc_html($company_label) . '</strong>'
        );

        $link = $profile_url !== ''
            ? $profile_url
            : ($user_id > 0
                ? admin_url('user-edit.php?user_id=' . $user_id)
                : admin_url('users.php'));

        return [
            'title' => __('Nuevo usuario registrado', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('check_shield'),
            'icon_slug' => 'check_shield',
            'tone'      => 'success',
            'badge'     => __('Nuevo', 'garantias-online-360vo'),
            'meta'      => $meta,
            'actions'   => $profile_url !== '' ? [
                [
                    'action' => 'view-user',
                    'title'  => __('Ver usuario', 'garantias-online-360vo'),
                    'url'    => $profile_url,
                ],
            ] : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_guarantee_note_added(array $record): ?array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $plate = isset($context['matricula']) ? sanitize_text_field((string) $context['matricula']) : '';
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $guarantee_label = isset($context['guarantee_label']) ? sanitize_text_field((string) $context['guarantee_label']) : '';
        if ($guarantee_label === '' && $plate !== '') {
            $guarantee_label = sprintf(__('Garantía %s', 'garantias-online-360vo'), $plate);
        }

        $actor_name = isset($record['actor_name']) ? sanitize_text_field((string) $record['actor_name']) : '';
        $note_excerpt = isset($context['note_excerpt']) ? sanitize_text_field((string) $context['note_excerpt']) : '';

        $body = $actor_name !== ''
            ? sprintf(
                /* translators: %1$s user name, %2$s license plate */
                __('%1$s ha añadido una nota en la garantía %2$s.', 'garantias-online-360vo'),
                $actor_name,
                $plate !== '' ? $plate : ($guarantee_label !== '' ? $guarantee_label : '#')
            )
            : __('Se añadió una nota en una garantía.', 'garantias-online-360vo');

        $link = $guarantee_id > 0
            ? admin_url('post.php?post=' . $guarantee_id . '&action=edit')
            : admin_url('edit.php?post_type=go_garantia');

        $meta = [];
        if ($guarantee_label !== '') {
            $meta[] = $this->meta_entry(__('Garantía', 'garantias-online-360vo'), $guarantee_label);
        }
        if ($note_excerpt !== '') {
            $meta[] = $this->meta_entry(__('Nota', 'garantias-online-360vo'), $note_excerpt);
        }

        return [
            'title'     => __('Nueva nota en garantía', 'garantias-online-360vo'),
            'body'      => $body,
            'link'      => $link,
            'icon'      => Svg::data_uri('note_event'),
            'icon_slug' => 'note_event',
            'tone'      => 'info',
            'badge'     => $plate !== '' ? $plate : '',
            'meta'      => $meta,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_guarantee_cancelled(array $record): ?array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $actor_name = isset($context['actor_name'])
            ? sanitize_text_field((string) $context['actor_name'])
            : sanitize_text_field((string) ($record['actor_name'] ?? ''));
        $guarantee_label = isset($context['guarantee_label']) ? sanitize_text_field((string) $context['guarantee_label']) : '';
        $plate = isset($context['matricula']) ? sanitize_text_field((string) $context['matricula']) : '';
        $reason = isset($context['reason']) ? sanitize_text_field((string) $context['reason']) : '';

        $title = __('Garantía cancelada', 'garantias-online-360vo');
        $body_parts = [];

        if ($actor_name !== '') {
            $body_parts[] = $plate !== ''
                ? sprintf(
                    /* translators: 1: actor name, 2: car plate */
                    __('%1$s ha cancelado la garantía %2$s.', 'garantias-online-360vo'),
                    $actor_name,
                    $plate
                )
                : sprintf(
                    /* translators: %s actor name */
                    __('%s ha cancelado una garantía.', 'garantias-online-360vo'),
                    $actor_name
                );
        } else {
            $body_parts[] = $plate !== ''
                ? sprintf(__('Se ha cancelado la garantía %s.', 'garantias-online-360vo'), $plate)
                : __('Se ha cancelado una garantía.', 'garantias-online-360vo');
        }

        $body = implode(' ', $body_parts);

        $meta = [];
        if ($plate !== '') {
            $meta[] = $this->meta_entry(__('Matrícula', 'garantias-online-360vo'), $plate);
        }
        if ($actor_name !== '') {
            $meta[] = $this->meta_entry(__('Cancelada por', 'garantias-online-360vo'), $actor_name);
        }
        if ($reason !== '') {
            $meta[] = $this->meta_entry(__('Motivo', 'garantias-online-360vo'), $reason);
        }

        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $context['vehicle_plate'] = $plate;

        return [
            'title' => $title,
            'body'  => $body,
            'link'  => $this->build_guarantee_link($guarantee_id, $context),
            'icon'      => Svg::data_uri('cancel_guarantee'),
            'icon_slug' => 'cancel_guarantee',
            'tone'      => 'danger',
            'meta'      => $meta,
            'category'  => 'guarantees',
            'context'   => [
                'guarantee_label' => $guarantee_label,
                'plate'           => $plate,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_guarantee_event(array $record, string $event_type): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $initiator = isset($context['initiator_label']) ? (string) $context['initiator_label'] : '';
        $vendor_id = isset($context['vendor_id']) ? (int) $context['vendor_id'] : 0;
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;

        if ($vendor_id <= 0 && $guarantee_id > 0) {
            $stored_vendor = get_post_meta($guarantee_id, 'garantia_contratada_concesionario_empresa_profesional', true);
            $normalized_vendor = $this->normalize_vendor_meta($stored_vendor);
            if ($normalized_vendor > 0) {
                $vendor_id = $normalized_vendor;
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
            : __('Sin identificar', 'garantias-online-360vo');

        $is_initialization = $event_type === 'guarantee.created';

        if ($is_initialization) {
            $body_suffix = __('ha iniciado la contratación de una nueva cobertura.', 'garantias-online-360vo');
            $status_label = __('Sin finalizar', 'garantias-online-360vo');
        } else {
            $body_suffix = sprintf(
                /* translators: %s: coverage name */
                __('ha contratado una Cobertura %s', 'garantias-online-360vo'),
                $plan_display
            );
        }

        $body = sprintf(
            '<strong>%1$s</strong> %2$s',
            esc_html($company_display),
            esc_html($body_suffix)
        );

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

        $title_text = $is_initialization
            ? __('Nueva garantía inicializada', 'garantias-online-360vo')
            : __('Nueva garantía', 'garantias-online-360vo');

        $badge_text = __('Nuevo', 'garantias-online-360vo');

        $tone = $is_initialization ? 'info' : 'primary';

        return [
            'title' => $title_text,
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('new_shield'),
            'icon_slug' => 'new_shield',
            'tone'      => $tone,
            'badge'     => $badge_text,
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
    private function build_payment_recorded(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $vendor_id = isset($context['vendor_id']) ? (int) $context['vendor_id'] : 0;

        $company_label = $this->resolve_company_label($vendor_id, $context, '');
        $company_display = $company_label !== ''
            ? $company_label
            : __('Profesional', 'garantias-online-360vo');

        $actor_label = '';
        if (isset($context['payment_actor_label'])) {
            $actor_label = $this->sanitize_plain_text((string) $context['payment_actor_label']);
        }

        $link = $this->build_guarantee_link($guarantee_id, $context);

        $body = $title !== ''
            ? sprintf(
                /* translators: %s: guarantee title */
                __('Se ha confirmado el pago de la garantía %s.', 'garantias-online-360vo'),
                $title
            )
            : sprintf(
                /* translators: %s: company name */
                __('Se ha confirmado un pago asociado a %s.', 'garantias-online-360vo'),
                $company_display
            );

        $meta = [];

        if ($company_label !== '') {
            $meta[] = $this->meta_entry(__('Profesional', 'garantias-online-360vo'), $company_label, 'actor');
        }

        if (! empty($context['payment_method_label'])) {
            $meta[] = $this->meta_entry(
                __('Método', 'garantias-online-360vo'),
                (string) $context['payment_method_label']
            );
        }

        $status_label = $this->resolve_status_from_context($context, $guarantee_id);
        if ($status_label !== '') {
            $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');
        }

        if (! empty($context['transfer_amount'])) {
            $meta[] = $this->meta_entry(
                __('Importe', 'garantias-online-360vo'),
                (string) $context['transfer_amount']
            );
        }

        if ($actor_label !== '') {
            $meta[] = $this->meta_entry(__('Confirmado por', 'garantias-online-360vo'), $actor_label, 'actor');
        }

        return [
            'title' => __('Pago confirmado', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'      => Svg::data_uri('payment'),
            'icon_slug' => 'payment',
            'tone'      => 'success',
            'meta'      => $meta,
            'actions'   => [
                [
                    'action' => 'open-guarantee',
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
    private function build_sepa_pending(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;
        $actor_name = isset($record['actor_name']) ? sanitize_text_field((string) $record['actor_name']) : '';

        $company = '';
        if (! empty($context['company_name'])) {
            $company = $this->sanitize_plain_text((string) $context['company_name']);
        } elseif ($actor_id > 0) {
            $labels = UserProfileResolver::get_vendor_labels($actor_id);
            if (! empty($labels['company']['trade_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['company']['trade_name']);
            } elseif (! empty($labels['company_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['company_name']);
            } elseif (! empty($labels['personal_full_name'])) {
                $company = $this->sanitize_plain_text((string) $labels['personal_full_name']);
            }
        }

        if ($company === '' && $actor_name !== '') {
            $company = $this->sanitize_plain_text($actor_name);
        }

        if ($company === '') {
            $company = __('Profesional', 'garantias-online-360vo');
        }

        $profile_url = isset($context['profile_url']) ? esc_url_raw((string) $context['profile_url']) : '';
        if ($profile_url === '' && $actor_id > 0) {
            $profile_url = $this->build_client_profile_url($actor_id);
        }
        if ($profile_url === '' && $actor_id > 0) {
            $profile_url = admin_url('user-edit.php?user_id=' . $actor_id);
        }

        $reference = ! empty($context['document_reference'])
            ? $this->sanitize_plain_text((string) $context['document_reference'])
            : '';

        $generated = ! empty($context['document_generated'])
            ? $this->format_datetime((string) $context['document_generated'])
            : '';

        $status_label = '';
        if (! empty($context['status_label'])) {
            $status_label = $this->sanitize_plain_text((string) $context['status_label']);
        }

        if ($status_label === '') {
            $status_label = __('Pendiente de firma', 'garantias-online-360vo');
        }

        $meta = [];

        $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');

        if ($reference !== '') {
            $meta[] = $this->meta_entry(__('Referencia', 'garantias-online-360vo'), $reference);
        }

        if ($generated !== '') {
            $meta[] = $this->meta_entry(__('Generado el', 'garantias-online-360vo'), $generated);
        }

        if (! empty($context['user_email'])) {
            $email = sanitize_email((string) $context['user_email']);
            if ($email !== '') {
                $meta[] = $this->meta_entry(__('Correo', 'garantias-online-360vo'), $email, 'actor');
            }
        }

        return [
            'title' => __('Solicitud de domiciliación', 'garantias-online-360vo'),
            'body'  => sprintf(
                '<strong>%1$s</strong> %2$s',
                esc_html($company),
                esc_html(__('ha generado un mandato SEPA pendiente de firma.', 'garantias-online-360vo'))
            ),
            'link'      => $profile_url !== '' ? $profile_url : admin_url('users.php'),
            'icon'      => Svg::data_uri('iban'),
            'icon_slug' => 'iban',
            'tone'      => 'warning',
            'badge'     => __('Nuevo', 'garantias-online-360vo'),
            'meta'      => $meta,
            'actions'   => $profile_url !== '' ? [
                [
                    'action' => 'open-profile',
                    'title'  => __('Abrir ficha del cliente', 'garantias-online-360vo'),
                    'url'    => $profile_url,
                ],
            ] : [],
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
        $profile_url = isset($context['profile_url']) ? esc_url_raw((string) $context['profile_url']) : '';
        if ($profile_url === '') {
            $target_user = $vendor_id > 0 ? $vendor_id : ($actor_id > 0 ? $actor_id : 0);
            if ($target_user > 0) {
                $profile_url = $this->build_client_profile_url($target_user);
            }
        }

        $link = $profile_url !== ''
            ? $profile_url
            : ($guarantee_id > 0
                ? get_permalink($guarantee_id)
                : admin_url('edit.php?post_type=garantia'));

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
     * @param array<string, mixed> $record
     */
    private function build_sepa_activated(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $target_id = isset($record['target_id']) ? (int) $record['target_id'] : 0;
        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;
        $user_id = $target_id > 0 ? $target_id : $actor_id;

        $name = '';
        if (! empty($context['user_name'])) {
            $name = $this->sanitize_plain_text((string) $context['user_name']);
        } elseif (! empty($context['client_name'])) {
            $name = $this->sanitize_plain_text((string) $context['client_name']);
        } elseif ($user_id > 0) {
            $name = $this->sanitize_plain_text(UserProfileResolver::get_personal_name($user_id));
        }
        if ($name === '') {
            $name = __('Profesional', 'garantias-online-360vo');
        }

        $company = '';
        if (! empty($context['company_name'])) {
            $company = $this->sanitize_plain_text((string) $context['company_name']);
        }

        $display_name = $company !== '' ? $company : $name;

        $reference = ! empty($context['document_reference'])
            ? $this->sanitize_plain_text((string) $context['document_reference'])
            : '';
        $status_label = ! empty($context['status_label'])
            ? $this->sanitize_plain_text((string) $context['status_label'])
            : __('SEPA válido y activo', 'garantias-online-360vo');

        $profile_url = isset($context['profile_url'])
            ? esc_url_raw((string) $context['profile_url'])
            : '';
        if ($profile_url === '' && $user_id > 0) {
            $profile_url = $this->build_client_profile_url($user_id);
        }
        if ($profile_url === '' && $user_id > 0) {
            $profile_url = admin_url('user-edit.php?user_id=' . $user_id);
        }

        $meta = [];
        if ($status_label !== '') {
            $meta[] = $this->meta_entry(__('Estado', 'garantias-online-360vo'), $status_label, 'status');
        }
        if ($reference !== '') {
            $meta[] = $this->meta_entry(__('Referencia', 'garantias-online-360vo'), $reference);
        }
        if (! empty($context['user_email'])) {
            $email = sanitize_email((string) $context['user_email']);
            if ($email !== '') {
                $meta[] = $this->meta_entry(__('Correo', 'garantias-online-360vo'), $email, 'actor');
            }
        }

        if (! empty($context['uploaded_by'])) {
            $uploader = $this->sanitize_plain_text((string) $context['uploaded_by']);
            if ($uploader !== '') {
                $meta[] = $this->meta_entry(__('Subido por', 'garantias-online-360vo'), $uploader, 'actor');
            }
        }

        return [
            'title' => __('Domiciliación bancaria activada', 'garantias-online-360vo'),
            'body'  => sprintf(
                '<strong>%1$s</strong> %2$s',
                esc_html($display_name),
                esc_html(__('ya tiene la domiciliación bancaria activa.', 'garantias-online-360vo'))
            ),
            'link'      => $profile_url !== '' ? $profile_url : admin_url('users.php'),
            'icon'      => Svg::data_uri('iban'),
            'icon_slug' => 'iban',
            'tone'      => 'success',
            'badge'     => __('Actualización', 'garantias-online-360vo'),
            'meta'      => $meta,
            'actions'   => $profile_url !== '' ? [
                [
                    'action' => 'open-profile',
                    'title'  => __('Abrir ficha del cliente', 'garantias-online-360vo'),
                    'url'    => $profile_url,
                ],
            ] : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_client_deleted(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');

        $client_id = isset($context['client_id']) ? (int) $context['client_id'] : 0;
        if ($client_id <= 0 && isset($record['target_id'])) {
            $client_id = (int) $record['target_id'];
        }

        $company_label = $this->resolve_company_label($client_id, $context, '');
        if ($company_label === '' && ! empty($context['client_name'])) {
            $company_label = $this->sanitize_plain_text((string) $context['client_name']);
        }
        if ($company_label === '' && ! empty($context['client_username'])) {
            $company_label = $this->sanitize_plain_text((string) $context['client_username']);
        }
        if ($company_label === '' && ! empty($context['client_email'])) {
            $company_label = $this->sanitize_plain_text((string) $context['client_email']);
        }
        if ($company_label === '') {
            $company_label = __('Cliente', 'garantias-online-360vo');
        }

        $actor_label = $this->resolve_actor_label($record, $context);
        $meta = [];
        if ($actor_label !== '') {
            $meta[] = $this->meta_entry(__('Eliminado por', 'garantias-online-360vo'), $actor_label, 'actor');
        }

        if (! empty($context['client_email'])) {
            $email = sanitize_email((string) $context['client_email']);
            if ($email !== '') {
                $meta[] = $this->meta_entry(__('Correo', 'garantias-online-360vo'), $email, 'actor');
            }
        }

        if (! empty($context['client_username'])) {
            $username = $this->sanitize_plain_text((string) $context['client_username']);
            if ($username !== '') {
                $meta[] = $this->meta_entry(__('Usuario', 'garantias-online-360vo'), $username);
            }
        }

        $body = $actor_label !== ''
            ? sprintf(
                /* translators: 1: actor label, 2: client label */
                __('%1$s ha eliminado al usuario <strong>%2$s</strong>.', 'garantias-online-360vo'),
                esc_html($actor_label),
                esc_html($company_label)
            )
            : sprintf(
                /* translators: %s client label */
                __('Se ha eliminado al usuario <strong>%s</strong>.', 'garantias-online-360vo'),
                esc_html($company_label)
            );

        return [
            'title' => __('Usuario eliminado', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => home_url('/garantias-online/clientes/'),
            'icon'      => Svg::data_uri('delete'),
            'icon_slug' => 'delete',
            'tone'      => 'warning',
            'badge'     => __('Eliminado', 'garantias-online-360vo'),
            'meta'      => $meta,
        ];
    }

    private function build_client_commercials_updated(array $record): ?array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $added = $this->extract_commercial_names($context['commercials_added'] ?? []);
        $removed = $this->extract_commercial_names($context['commercials_removed'] ?? []);

        if (empty($added) && empty($removed)) {
            return null;
        }

        $client_id = isset($context['client_id']) ? (int) $context['client_id'] : 0;
        if ($client_id <= 0 && isset($record['target_id'])) {
            $client_id = (int) $record['target_id'];
        }

        $company_context = $context;
        if (! isset($company_context['vendor_id'])) {
            $company_context['vendor_id'] = $client_id;
        }
        if (! isset($company_context['vendor_name']) && ! empty($context['client_name'])) {
            $company_context['vendor_name'] = $context['client_name'];
        }

        $company_label = $this->resolve_company_label($client_id, $company_context, '');
        if ($company_label === '' && ! empty($context['client_name'])) {
            $company_label = $this->sanitize_plain_text((string) $context['client_name']);
        }
        if ($company_label === '') {
            $company_label = __('Cliente', 'garantias-online-360vo');
        }

        $client_url = $this->build_client_profile_url($client_id);
        if ($client_url === '' && $client_id > 0) {
            $client_url = admin_url('user-edit.php?user_id=' . $client_id);
        }

        $actor_label = $this->resolve_actor_label($record, $context);
        $meta = [];
        if ($actor_label !== '') {
            $meta[] = $this->meta_entry(__('Usuario', 'garantias-online-360vo'), $actor_label, 'actor');
        }

        $actions = $client_url !== '' ? [
            [
                'action' => 'view-client',
                'title'  => __('Ver ficha cliente', 'garantias-online-360vo'),
                'url'    => $client_url,
            ],
        ] : [];

        $title = '';
        $tone = 'info';
        $body_segments = [];

        if (! empty($added)) {
            $names = $this->format_human_list($added);
            $body_segments[] = sprintf(
                count($added) === 1
                    ? __('%1$s ha sido asignado a <b>%2$s</b> como comercial.', 'garantias-online-360vo')
                    : __('%1$s han sido asignados como comerciales para <b>%2$s</b>.', 'garantias-online-360vo'),
                esc_html($names),
                esc_html($company_label)
            );
            $title = __('Nuevo comercial asignado', 'garantias-online-360vo');
            $tone = 'success';
        }

        if (! empty($removed)) {
            $names = $this->format_human_list($removed);
            $body_segments[] = sprintf(
                count($removed) === 1
                    ? __('%1$s ha sido desasignado como comercial de <b>%2$s</b>.', 'garantias-online-360vo')
                    : __('%1$s han sido desasignados como comerciales de <b>%2$s</b>.', 'garantias-online-360vo'),
                esc_html($names),
                esc_html($company_label)
            );

            if ($title === '') {
                $title = __('Comercial desasignado', 'garantias-online-360vo');
            } elseif (! empty($added)) {
                $title = __('Asignaciones de comerciales actualizadas', 'garantias-online-360vo');
            }

            $tone = empty($added) ? 'warning' : 'info';
        }

        $body_segments = array_values(array_filter($body_segments));
        if (empty($body_segments)) {
            return null;
        }

        return [
            'title'     => $title !== '' ? $title : __('Asignaciones de comerciales actualizadas', 'garantias-online-360vo'),
            'body'      => implode(' ', $body_segments),
            'link'      => $client_url !== '' ? $client_url : admin_url('users.php'),
            'icon'      => Svg::data_uri('person_add'),
            'icon_slug' => 'person_add',
            'tone'      => $tone,
            'meta'      => $meta,
            'actions'   => $actions,
        ];
    }

    private function build_client_profile_url(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

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

        foreach (['plan_label', 'plan_name', 'plan_title', 'plan_display_name', 'plan', 'modalidad_label', 'coverage_label'] as $key) {
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

        $plan_id = (int) ($context['plan_id'] ?? 0);
        if ($plan_id <= 0) {
            $plan_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_garantia', true);
        }
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
        $payment_method = isset($context['payment_method'])
            ? sanitize_key((string) $context['payment_method'])
            : '';

        $status_slug = '';
        if (! empty($context['current_state'])) {
            $status_slug = sanitize_key((string) $context['current_state']);
        }

        $status_label = '';
        if (! empty($context['current_state_label'])) {
            $status_label = wp_strip_all_tags((string) $context['current_state_label']);
        }

        if ($status_label === '' && $status_slug !== '') {
            $status_label = $this->status_label($status_slug);
        }

        if ($status_label === '' && $guarantee_id > 0) {
            $stored = (string) get_post_meta($guarantee_id, 'estado_garantia_estado_contratacion', true);
            if ($stored !== '') {
                $status_slug = sanitize_key($stored);
                $status_label = $this->status_label($status_slug);
            }
        }

        if ($payment_method !== '' && strpos($payment_method, 'domiciliacion') !== false) {
            if ($status_slug === '' || in_array($status_slug, ['pendiente_pago', 'pending_payment', 'pending_cobro', 'pendiente_cobro', 'activada', 'publish'], true)) {
                $status_label = __('Pend. Domiciliación', 'garantias-online-360vo');
            }
        }

        return $status_label;
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

        if ($vendor_id <= 0 && ! empty($context['vendor_id'])) {
            $vendor_id = (int) $context['vendor_id'];
        }

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

    private function normalize_vendor_meta($raw): int
    {
        if (is_array($raw)) {
            if (isset($raw['ID'])) {
                return (int) $raw['ID'];
            }
            if (isset($raw['id'])) {
                return (int) $raw['id'];
            }
            if (isset($raw['value'])) {
                return (int) $raw['value'];
            }
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $raw_string = is_string($raw) ? trim($raw) : '';
        if ($raw_string !== '' && ctype_digit($raw_string)) {
            return (int) $raw_string;
        }

        return 0;
    }

    private function sanitize_plain_text($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = wp_strip_all_tags($value);

        return trim($value);
    }

    /**
     * @param array<int, mixed> $entries
     * @return string[]
     */
    private function extract_commercial_names($entries): array
    {
        if (! is_array($entries) || empty($entries)) {
            return [];
        }

        $names = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = '';
            if (! empty($entry['name'])) {
                $name = $this->sanitize_plain_text((string) $entry['name']);
            }
            if ($name === '' && ! empty($entry['email'])) {
                $name = sanitize_email((string) $entry['email']);
            }
            if ($name === '' && ! empty($entry['username'])) {
                $name = sanitize_user((string) $entry['username'], true);
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param string[] $items
     */
    private function format_human_list(array $items): string
    {
        $sanitized = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $clean = $this->sanitize_plain_text($item);
            if ($clean !== '') {
                $sanitized[] = $clean;
            }
        }

        $count = count($sanitized);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $sanitized[0];
        }

        if ($count === 2) {
            return $sanitized[0] . ' y ' . $sanitized[1];
        }

        $last = array_pop($sanitized);

        return implode(', ', $sanitized) . ' y ' . $last;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $context
     */
    private function resolve_actor_label(array $record, array $context = []): string
    {
        if (! empty($record['actor_name'])) {
            $label = $this->sanitize_plain_text((string) $record['actor_name']);
            if ($label !== '') {
                return $label;
            }
        }

        if (! empty($context['actor_label']) && is_string($context['actor_label'])) {
            $label = $this->sanitize_plain_text($context['actor_label']);
            if ($label !== '') {
                return $label;
            }
        }

        if (! empty($record['actor_email'])) {
            $email = sanitize_email((string) $record['actor_email']);
            if ($email !== '') {
                return $email;
            }
        }

        $actor_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;
        if ($actor_id > 0) {
            $resolved = UserProfileResolver::get_personal_name($actor_id);
            if ($resolved !== '') {
                return $this->sanitize_plain_text($resolved);
            }

            $user = get_user_by('id', $actor_id);
            if ($user instanceof WP_User) {
                if (! empty($user->display_name)) {
                    $display = $this->sanitize_plain_text($user->display_name);
                    if ($display !== '') {
                        return $display;
                    }
                }

                if (! empty($user->user_email)) {
                    $email = sanitize_email((string) $user->user_email);
                    if ($email !== '') {
                        return $email;
                    }
                }

                if (! empty($user->user_login)) {
                    $username = sanitize_user((string) $user->user_login, true);
                    if ($username !== '') {
                        return $username;
                    }
                }
            }
        }

        return '';
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

    private function format_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if (! $timestamp) {
            return '';
        }

        $date_format = get_option('date_format');
        if (! is_string($date_format) || $date_format === '') {
            $date_format = 'd/m/Y';
        }

        $time_format = get_option('time_format');
        if (! is_string($time_format) || $time_format === '') {
            $time_format = 'H:i';
        }

        return wp_date($date_format . ' ' . $time_format, $timestamp);
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
