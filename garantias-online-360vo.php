<?php
/**
 * Plugin Name: Garantías Online - 360VO
 * Description: Sistema completo de gestión de garantías de vehículos. Permite registrar, gestionar y consultar garantías de manera sencilla y eficiente.
 * Plugin URI:  https://360vo.es/
 * Version:     6.2.0
 * Author:      Carlos Marín - 360VO
 * Text Domain: garantias-online-360vo
 */

if (! defined('ABSPATH')) {
    exit;
}

// Ruta al archivo principal
if (! defined('GARANTIAS360VO__FILE__')) {
    define('GARANTIAS360VO__FILE__', __FILE__);
}

// Cargamos Autoloader
require_once __DIR__ . '/src/Autoloader.php';

use GarantiasOnline360VO\Autoloader;
use GarantiasOnline360VO\Plugin;

// Iniciar Autoload
Autoloader::run();
// Arrancar Plugin cuando todos los plugins estén cargados
add_action('plugins_loaded', [Plugin::class, 'run']);
