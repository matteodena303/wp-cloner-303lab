<?php
/**
 * Plugin Name: WP Cloner
 * Description: Strumento di clonazione per WordPress: esporta e importa file e database.
 * Version: 0.2.2
 * Author: 303Lab
 *
 * Questo plugin offre una procedura asincrona per l'esportazione del sito, con barra di progresso e opzioni di compressione.
 */

// Interrompe l'esecuzione se WordPress non è definito.
if (!defined('ABSPATH')) {
    exit;
}

// Carica le classi necessarie.
require_once __DIR__ . '/includes/class-exporter.php';
require_once __DIR__ . '/includes/class-importer.php';
require_once __DIR__ . '/includes/class-packager.php';
require_once __DIR__ . '/includes/class-rewriter.php';
// Carica il supporto WP-CLI se presente.
require_once __DIR__ . '/includes/class-cli.php';

/**
 * Aggiunge la pagina di amministrazione sotto Strumenti.
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'WP Cloner',
        'WP Cloner',
        'manage_options',
        'wpcloner',
        'wpcloner_admin_page'
    );
});

/**
 * Callback che include la pagina di amministrazione.
 */
function wpcloner_admin_page()
{
    include __DIR__ . '/admin/page.php';
}

// Hook per esportazione asincrona e importazione.
add_action('admin_post_wpcloner_export', 'wpcloner_enqueue_export');
add_action('admin_post_wpcloner_import', 'wpcloner_handle_import');
// Hook per l'import di pacchetti già presenti nel server.
add_action('admin_post_wpcloner_import_existing', 'wpcloner_handle_import_existing');

// Hook per esecuzione del job di esportazione.
add_action('wpcloner_run_export', 'wpcloner_run_export', 10, 1);

// Endpoints AJAX.
add_action('wp_ajax_wpcloner_progress', 'wpcloner_ajax_progress');
add_action('wp_ajax_wpcloner_download', 'wpcloner_ajax_download');

/**
 * Invia un job di esportazione alla coda asincrona.
 *
 * Legge l'opzione di compressione dal form e salva gli stati nei transient.
 * Pianifica quindi l'esportazione tramite Action Scheduler se disponibile, altrimenti WP-Cron.
 */
function wpcloner_enqueue_export()
{
    if (!current_user_can('manage_options')) {
        wp_die('Denied');
    }
    check_admin_referer('wpcloner_export');
    $compression = isset($_POST['compression']) ? sanitize_key($_POST['compression']) : 'zip';
    if (!in_array($compression, ['zip', 'zipgz'], true)) {
        $compression = 'zip';
    }
    $job_id = 'wpcloner_' . wp_generate_password(13, false, false) . '_' . time();
    $args   = [
        'compression' => $compression,
    ];
    set_transient($job_id . '_args', $args, HOUR_IN_SECONDS);
    set_transient($job_id . '_progress', 0, HOUR_IN_SECONDS);
    set_transient($job_id . '_status', 'running', HOUR_IN_SECONDS);
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('wpcloner_run_export', ['job_id' => $job_id], 'wpcloner', true);
    } else {
        wp_schedule_single_event(time(), 'wpcloner_run_export', [$job_id]);
    }
    wp_safe_redirect(add_query_arg('wpcloner_job', $job_id, admin_url('tools.php?page=wpcloner')));
    exit;
}

/**
 * Esegue il job di esportazione: crea dump DB, pacchetto ZIP, eventuale GZIP.
 * Aggiorna i transient per progresso e stato.
 *
 * @param mixed $job_id_arg
 */
function wpcloner_run_export($job_id_arg)
{
    $job_id = is_array($job_id_arg) && isset($job_id_arg['job_id']) ? $job_id_arg['job_id'] : $job_id_arg;
    $args   = get_transient($job_id . '_args');
    if (!$args) {
        set_transient($job_id . '_status', 'error', HOUR_IN_SECONDS);
        return;
    }
    $compression = $args['compression'] ?? 'zip';
    $exporter    = new WPCloner_Exporter();
    $packager    = new WPCloner_Packager();
    set_transient($job_id . '_progress', 5, HOUR_IN_SECONDS);
    $manifest = $exporter->build_manifest();
    set_transient($job_id . '_progress', 15, HOUR_IN_SECONDS);
    $dump = $exporter->dump_database();
    if (!$dump) {
        set_transient($job_id . '_status', 'error', HOUR_IN_SECONDS);
        return;
    }
    set_transient($job_id . '_progress', 30, HOUR_IN_SECONDS);
    $include_paths = [WP_CONTENT_DIR, $dump];
    set_transient($job_id . '_progress', 50, HOUR_IN_SECONDS);
    // Determina la directory di destinazione per il pacchetto.
    $uploads = wp_upload_dir();
    // Usa una sottodirectory dedicata per i pacchetti in modo da poterli individuare facilmente.
    $dest_dir = trailingslashit($uploads['basedir']) . 'wpcloner';
    // Assicurati che la directory esista.
    if (!file_exists($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }
    $dest = trailingslashit($dest_dir) . 'wpcloner-' . date('Ymd-His') . '.zip';
    $ok   = $packager->zip($dest, $include_paths, $manifest);
    if (file_exists($dump)) {
        @unlink($dump);
    }
    if (!$ok) {
        set_transient($job_id . '_status', 'error', HOUR_IN_SECONDS);
        return;
    }
    set_transient($job_id . '_progress', 75, HOUR_IN_SECONDS);
    if ($compression === 'zipgz') {
        $gz_path = $dest . '.gz';
        $data    = file_get_contents($dest);
        if ($data !== false) {
            $gzdata = gzencode($data, 9);
            file_put_contents($gz_path, $gzdata);
            @unlink($dest);
            $dest = $gz_path;
        }
    }
    set_transient($job_id . '_file', $dest, HOUR_IN_SECONDS);
    set_transient($job_id . '_progress', 100, HOUR_IN_SECONDS);
    set_transient($job_id . '_status', 'complete', HOUR_IN_SECONDS);
}

/**
 * Endpoint AJAX che restituisce lo stato di avanzamento del job.
 */
function wpcloner_ajax_progress()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }
    $job_id = isset($_GET['job_id']) ? sanitize_text_field($_GET['job_id']) : '';
    if (!$job_id) {
        wp_send_json_error();
    }
    $progress = (int) get_transient($job_id . '_progress');
    $status   = get_transient($job_id . '_status') ?: 'running';
    wp_send_json_success(['progress' => $progress, 'status' => $status]);
}

/**
 * Endpoint AJAX che invia in download il file generato.
 */
function wpcloner_ajax_download()
{
    if (!current_user_can('manage_options')) {
        wp_die('Denied');
    }
    $job_id = isset($_GET['job_id']) ? sanitize_text_field($_GET['job_id']) : '';
    $file = $job_id ? get_transient($job_id . '_file') : '';
    // Se non troviamo il percorso salvato o il file non esiste più, prova a localizzarlo
    if (!$file || !file_exists($file)) {
        // Analizza l'ID per estrarre una possibile data/ora (ultima parte dopo l'underscore).
        $timestamp = 0;
        if (strpos($job_id, '_') !== false) {
            $parts = explode('_', $job_id);
            $last  = end($parts);
            if (is_numeric($last)) {
                $timestamp = (int) $last;
            }
        }
        // Cerca nella directory uploads/wpcloner per pacchetti generati nelle ultime 2 ore.
        $uploads   = wp_upload_dir();
        $packages_dir = trailingslashit($uploads['basedir']) . 'wpcloner';
        if (file_exists($packages_dir)) {
            $candidates = glob(trailingslashit($packages_dir) . 'wpcloner-*.zip*');
            if (!empty($candidates)) {
                // Ordina per data di modifica decrescente.
                usort($candidates, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                foreach ($candidates as $candidate) {
                    // Se non abbiamo timestamp specifico, prendi il più recente entro 2 ore.
                    if ($timestamp === 0) {
                        if (filemtime($candidate) >= time() - 2 * HOUR_IN_SECONDS) {
                            $file = $candidate;
                            break;
                        }
                    } else {
                        // Confronta la data nel nome con l'ID del job.
                        if (preg_match('/wpcloner-(\d{8}-\d{6})/', basename($candidate), $m)) {
                            $file_time = strtotime(str_replace('-', '', substr($m[1], 0, 8)) . substr($m[1], 9));
                            // Se la differenza è entro 3600 secondi, considera valido.
                            if (abs($file_time - $timestamp) <= 3600) {
                                $file = $candidate;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    // Verifica nuovamente l'esistenza del file.
    if (!$file || !file_exists($file)) {
        wp_die('Pacchetto non trovato');
    }
    $filename = basename($file);
    $ctype    = (substr($filename, -3) === '.gz') ? 'application/gzip' : 'application/zip';
    header('Content-Type: ' . $ctype);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    // Rimuovi il pacchetto e la transient per non lasciare file orfani.
    @unlink($file);
    delete_transient($job_id . '_file');
    exit;
}

/**
 * Gestisce l'import sincrono di un pacchetto.
 */
function wpcloner_handle_import()
{
    if (!current_user_can('manage_options')) {
        wp_die('Denied');
    }
    check_admin_referer('wpcloner_import');
    if (empty($_FILES['wpcloner_package']['tmp_name'])) {
        wp_die('Pacchetto mancante');
    }
    $file = $_FILES['wpcloner_package'];
    $tmp  = $file['tmp_name'];
    $importer = new WPCloner_Importer();
    $ok = $importer->import_package($tmp, [
        'new_url'      => isset($_POST['new_url']) ? esc_url_raw($_POST['new_url']) : '',
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

/**
 * Gestisce l'import di un pacchetto già salvato sul server (senza upload).
 *
 * Consente di importare pacchetti presenti nella directory wp-content/uploads/wpcloner.
 */
function wpcloner_handle_import_existing()
{
    if (!current_user_can('manage_options')) {
        wp_die('Denied');
    }
    check_admin_referer('wpcloner_import_existing');
    $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
    if (!$file_path || !file_exists($file_path)) {
        wp_die('Pacchetto mancante');
    }
    $importer = new WPCloner_Importer();
    $ok = $importer->import_package($file_path, [
        'new_url'      => isset($_POST['new_url']) ? esc_url_raw($_POST['new_url']) : '',
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