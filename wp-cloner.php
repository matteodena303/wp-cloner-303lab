<?php


// Prepara lista file da includere
$include_paths = [
WP_CONTENT_DIR, // wp-content completo
$dump_path,
];


$packager = new WPCloner_Packager();
$ok = $packager->zip($zip_path, $include_paths, $manifest);


// Pulisci tmp
if (file_exists($dump_path)) @unlink($dump_path);


if (!$ok) wp_die('Errore nella creazione del pacchetto');


// Fornisci download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zip_path) . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
// opzionale: non conservare il file sul server
@unlink($zip_path);
exit;
}


function wpcloner_handle_import() {
if (!current_user_can('manage_options')) wp_die('Denied');
check_admin_referer('wpcloner_import');


if (empty($_FILES['wpcloner_package']['tmp_name'])) wp_die('Pacchetto mancante');


$file = $_FILES['wpcloner_package'];
$tmp = $file['tmp_name'];


$importer = new WPCloner_Importer();
$ok = $importer->import_package($tmp, [
'new_url' => isset($_POST['new_url']) ? esc_url_raw($_POST['new_url']) : '',
'strip_emails' => !empty($_POST['strip_emails']),
'disable_mail' => !empty($_POST['disable_mail']),
]);


if (!$ok) {
wp_safe_redirect(add_query_arg('wpcloner_status', 'error', admin_url('tools.php?page=wpcloner')));
exit;
}


wp_safe_redirect(add_query_arg('wpcloner_status', 'success', admin_url('tools.php?page=wpcloner')));
exit;
}


// Attiva comandi WP-CLI se disponibili
if (defined('WP_CLI') && WP_CLI) {
WP_CLI::add_command('cloner', 'WPCloner_CLI');
}