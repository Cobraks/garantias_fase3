<?php
if (! defined('ABSPATH')) exit;
$is_list_page  = false;
$is_add_guarantee = false;
$is_auth_page = false;
$is_dashboard_page = false;
\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_auth_page', 'is_dashboard_page'));

?>
<h1>Averías</h1>
<p>Listado de averías aquí…</p>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part('footer', compact('is_dashboard_page'));
