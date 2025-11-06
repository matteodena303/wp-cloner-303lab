<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsabile dell'importazione di un pacchetto zip creato da WP Cloner.
 *
 * L'importer ripristina i file all'interno di wp-content, importa il dump SQL
 * e applica una riscrittura sicura degli URL e dei percorsi nel database.
 */
class WPCloner_Importer
{
    /**
     * Importa un pacchetto zip.
     *
     * @param string $zip_tmp_path Percorso temporaneo al pacchetto zip caricato.
     * @param array  $opts          Opzioni facoltative:
     *                              - 'new_url' (string) Nuovo URL del sito.
     *                              - 'strip_emails' (bool) Anonimizza gli indirizzi email.
     *                              - 'disable_mail' (bool) Disabilita l'invio di mail.
     *
     * @return bool True se l'import è andato a buon fine, false altrimenti.
     */
    public function import_package($zip_tmp_path, array $opts = []): bool
    {
        $uploads = wp_upload_dir();
        $workdir = trailingslashit($uploads['basedir']) . 'wpcloner-import-' . time();
        wp_mkdir_p($workdir);

        // Estrai il pacchetto nel working directory.
        $packager = new WPCloner_Packager();
        if (!$packager->unzip($zip_tmp_path, $workdir)) {
            return false;
        }

        // 1) Ripristina wp-content (merge ricorsivo).
        $src_content = trailingslashit($workdir) . 'wp-content';
        if (is_dir($src_content)) {
            $this->rcopy($src_content, WP_CONTENT_DIR);
        }

        // 2) Import SQL dal file .sql incluso.
        $dump_files = glob($workdir . '/*.sql');
        if (!$dump_files) {
            // prova a cercare ricorsivamente
            $dump_files = glob($workdir . '/**/*.sql');
        }
        if (empty($dump_files)) {
            return false;
        }
        $sql_file = $dump_files[0];
        if (!$this->import_sql($sql_file)) {
            return false;
        }

        // 3) Rewriter per URL e percorsi.
        $map = [];
        $manifest_file = trailingslashit($workdir) . 'manifest.json';
        if (file_exists($manifest_file)) {
            $manifest = json_decode(file_get_contents($manifest_file), true);
            if ($manifest && !empty($manifest['home'])) {
                $old = rtrim($manifest['home'], '/');
                $new = rtrim(($opts['new_url'] ?? get_option('home')), '/');
                if ($old && $new && $old !== $new) {
                    $map[$old] = $new;
                }
            }
            // Mappa della directory di upload
            if (!empty($manifest['upload_basedir'])) {
                $current_upload_base = wp_normalize_path(wp_get_upload_dir()['basedir']);
                $map[wp_normalize_path($manifest['upload_basedir'])] = $current_upload_base;
            }
        }
        if (!empty($map)) {
            $rewriter = new WPCloner_Rewriter();
            $rewriter->run($map);
        }

        // 4) Post-migrazione: flush rewrite rules, rigenera thumbnails, ecc.
        $this->post_migration($opts);

        // 5) Rimuovi la directory di lavoro.
        $this->rrmdir($workdir);
        return true;
    }

    /**
     * Importa il file SQL nel database corrente.
     * Utilizza mysqldump via CLI se disponibile, altrimenti esegue le query via PHP.
     *
     * @param string $sql_file
     * @return bool
     */
    private function import_sql($sql_file): bool
    {
        // Tentativo via CLI per performance
        if (function_exists('exec')) {
            $cmd = $this->build_mysql_cmd($sql_file);
            if ($cmd) {
                @exec($cmd, $out, $code);
                if ($code === 0) {
                    return true;
                }
            }
        }
        // Fallback: import via PHP
        global $wpdb;
        $sql = file_get_contents($sql_file);
        // Suddividi le query sul carattere ';' seguito da newline
        $queries = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($queries as $q) {
            if ($q === '') {
                continue;
            }
            $wpdb->query($q);
        }
        return true;
    }

    /**
     * Costruisce la riga di comando per importare lo SQL via mysql CLI.
     *
     * @param string $sql_file
     * @return string|false
     */
    private function build_mysql_cmd($sql_file)
    {
        if (!function_exists('exec') || !defined('DB_NAME')) {
            return false;
        }
        $args = [];
        $args[] = 'mysql';
        $args[] = escapeshellarg(DB_NAME);
        if (defined('DB_USER')) {
            $args[] = '-u' . escapeshellarg(DB_USER);
        }
        if (defined('DB_PASSWORD') && DB_PASSWORD !== '') {
            $args[] = '-p' . escapeshellarg(DB_PASSWORD);
        }
        if (defined('DB_HOST')) {
            $args[] = '-h' . escapeshellarg(DB_HOST);
        }
        $cmd = implode(' ', $args) . ' < ' . escapeshellarg($sql_file);
        return $cmd;
    }

    /**
     * Copia ricorsivamente i file e le directory da sorgente a destinazione.
     *
     * @param string $src
     * @param string $dest
     * @return void
     */
    private function rcopy($src, $dest)
    {
        $src = wp_normalize_path($src);
        $dest = wp_normalize_path($dest);
        if (is_dir($src)) {
            @wp_mkdir_p($dest);
            $dir = opendir($src);
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $s = $src . '/' . $file;
                $d = $dest . '/' . $file;
                if (is_dir($s)) {
                    $this->rcopy($s, $d);
                } else {
                    @wp_mkdir_p(dirname($d));
                    copy($s, $d);
                }
            }
            closedir($dir);
        } else {
            @wp_mkdir_p(dirname($dest));
            copy($src, $dest);
        }
    }

    /**
     * Rimuove ricorsivamente una directory.
     *
     * @param string $dir
     * @return void
     */
    private function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    /**
     * Esegue operazioni di post-migrazione, come flush dei permalink e rigenerazione miniature.
     * Può essere estesa con logica aggiuntiva (es. anonymizzazione email, disabilitazione mail ecc.).
     *
     * @param array $opts
     * @return void
     */
    private function post_migration(array $opts)
    {
        // Aggiorna rewrite rules
        flush_rewrite_rules();

        // Rigenera le miniature se il tema le richiede
        if (function_exists('wp_generate_attachment_metadata')) {
            // Potenzialmente si potrebbero scansionare gli allegati e rigenerare le thumbs.
        }

        // Opzionale: anonimizza email o disabilita cron/email in ambienti di staging.
        if (!empty($opts['strip_emails'])) {
            global $wpdb;
            $wpdb->query("UPDATE {$wpdb->users} SET user_email = CONCAT(MD5(user_login), '@example.com')");
        }
        if (!empty($opts['disable_mail'])) {
            // Imposta un flag transiente per bloccare invii mail, a discrezione dell'utente.
            update_option('wpcloner_disable_mail', 1);
        }
    }
}