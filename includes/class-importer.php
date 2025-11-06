<?php
if (!defined('ABSPATH')) {
    exit;
}


class WPCloner_Importer
{
    public function import_package($zip_tmp_path, array $opts = []): bool
    {
        $uploads = wp_upload_dir();
        $workdir = trailingslashit($uploads['basedir']) . 'wpcloner-import-' . time();
        wp_mkdir_p($workdir);


        $packager = new WPCloner_Packager();
        if (!$packager->unzip($zip_tmp_path, $workdir)) return false;


        // 1) Ripristina wp-content (merge)
        $src_content = trailingslashit($workdir) . 'wp-content';
        if (is_dir($src_content)) {
            $this->rcopy($src_content, WP_CONTENT_DIR);
        }


        // 2) Import SQL
        $dump = glob($workdir . '/*.sql');
        if (!$dump) $dump = glob($workdir . '/**/*.sql');
        if (empty($dump)) return false;
        $sql_file = $dump[0];
        if (!$this->import_sql($sql_file)) return false;


        // 3) Rewriter (URL e path)
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
        }
        // Path locali tipici (uploads)
        $upload_base_current = wp_normalize_path(wp_get_upload_dir()['basedir']);
        if (isset($manifest['upload_basedir'])) {
            $map[wp_normalize_path($manifest['upload_basedir'])] = $upload_base_current;
        }


        if (!empty($map)) {
            $rewriter = new WPCloner_Rewriter();
            $rewriter->run($map);
        }


        // 4) Post migrazione
        $this->post_migration($opts);


        // Cleanup
        $this->rrmdir($workdir);
        return true;
    }


    private function import_sql($sql_file): bool
    {
        // Tentativo via CLI mysql per performance
        if (function_exists('exec')) {
            $cmd = $this->build_mysql_cmd($sql_file);
            if ($cmd) {
                @exec($cmd, $out, $code);
                if ($code === 0) return true;
            }
        }
        // Fallback: import via PHP (semplice, chunk per ;)
        global $wpdb;
        $sql = file_get_contents($sql_file);
        $queries = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($queries as $q) {
            if ($q === '') continue;
            $wpdb->query($q);
        }
        return true;
    }


    private function build_mysql_cmd($sql_file)
    {
        if (!defined('DB_NAME')) return false;
        $args = [];
    }
}
