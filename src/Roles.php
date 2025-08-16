<?php

/**
 * Define y registra los roles personalizados para Garantías Online 360VO
 *
 * @package GarantiasOnline360VO
 *
 * @todo Revisar y ajustar capacidades de roles: particulares, profesionales, gestorías y comerciales según requisitos futuros.
 */

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Roles
{
    /**
     * Añade los roles al activar el plugin
     */
    public static function add_roles(): void
    {
        // Particular: solo lectura de garantías propias
        $caps_particular = [
            'read'                     => true,
            'edit_garantia'            => false,
            'edit_garantias'           => false,
            'publish_garantias'        => false,
            'delete_garantia'          => false,
            'delete_garantias'         => false,
            'edit_others_garantias'    => false,
            'delete_others_garantias'  => false,
            'edit_published_garantias' => false,
            'delete_published_garantias' => false,
            'read_private_garantias'   => true,
        ];

        // Profesional: puede crear y gestionar sus propias garantías
        $caps_profesional = [
            'read'                     => true,
            'edit_garantia'            => true,
            'edit_garantias'           => true,
            'publish_garantias'        => true,
            'delete_garantia'          => true,
            'delete_garantias'         => true,
            'edit_others_garantias'    => false,
            'delete_others_garantias'  => false,
            'edit_published_garantias' => true,
            'delete_published_garantias' => true,
            'read_private_garantias'   => true,
        ];

        // Gestoría: mismas capacidades que profesional
        $caps_gestoria = $caps_profesional;

        // Comercial: solo lectura de garantías (propias y asignadas)
        $caps_comercial = [
            'read'                     => true,
            'edit_garantia'            => false,
            'edit_garantias'           => false,
            'publish_garantias'        => false,
            'delete_garantia'          => false,
            'delete_garantias'         => false,
            'edit_others_garantias'    => false,
            'delete_others_garantias'  => false,
            'edit_published_garantias' => false,
            'delete_published_garantias' => false,
            'read_private_garantias'   => true,
        ];

        add_role(
            'go_particular',
            __('Particular', 'garantias-online-360vo'),
            $caps_particular
        );

        add_role(
            'go_profesional',
            __('Profesional', 'garantias-online-360vo'),
            $caps_profesional
        );

        add_role(
            'go_gestoria',
            __('Gestoría', 'garantias-online-360vo'),
            $caps_gestoria
        );

        add_role(
            'go_comercial',
            __('Comercial', 'garantias-online-360vo'),
            $caps_comercial
        );

        // Ensure administrators can manage guarantees
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($caps_profesional as $cap => $grant) {
                if ($grant) {
                    $admin->add_cap($cap);
                }
            }
        }
    }

    /**
     * Elimina los roles al desactivar el plugin
     */
    public static function remove_roles(): void
    {
        remove_role('go_particular');
        remove_role('go_profesional');
        remove_role('go_gestoria');
        remove_role('go_comercial');
    }
}
