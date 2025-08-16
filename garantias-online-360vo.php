<?php
/**
 * Plugin Name: Garantías Online - 360VO
 * Description: Gestión de garantías de vehículos paso a paso con CSS/JS globales.
* Version:     0.1.17
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
// Arrancar Plugin
Plugin::run();
