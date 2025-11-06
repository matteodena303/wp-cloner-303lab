<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsabile dell'esportazione del sito in un pacchetto zip.
 *
 * Genera un file manifest con le informazioni del sito, effettua il dump del database
 * e restituisce i percorsi dei file da includere nel pacchetto.
 */
class WPCloner_Exporter
{
    /**
     * Genera il manifest contenente informazioni chiave del sito.
     *
     * @return array
     */
    public function build_manifest(): array
    {
        global $wpdb;
        $uploads = wp_get_upload_dir();
        return [
            'siteurl'        => get_option('siteurl'),
            'home'           => get_option('home'),
            'table_prefix'   => $wpdb->prefix,
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => phpversion(),
            'charset'        => get_option('blog_charset'),
            'active_plugins' => get_option('active_plugins', []),
            'datetime'       => current_time('mysql'),
            'upload_basedir' => $uploads['basedir'],
        ];
    }

    /**
     * Esegue il dump del database corrente. Se possibile usa mysqldump, altrimenti
     * esegue un fallback in PHP generando manualmente le query di CREATE e INSERT.
     *
     * @return string|false Percorso al file SQL creato, o false in caso di errore.
     */
    public function dump_database()
    {
        global $wpdb;
        $uploads = wp_upload_dir();
        $tmp = trailingslashit($uploads['basedir']) . 'wpcloner-sqldump-' . time() . '.sql';

        // 1) Prova mysqldump
        $cmd = $this->build_mysqldump_cmd($tmp);
        if ($cmd) {
            @exec($cmd, $out, $code);
            if (file_exists($tmp) && filesize($tmp) > 0) {
                return $tmp;
            }
        }

        // 2) Fallback PHP
        $tables = $wpdb->get_col('SHOW TABLES');
        $fh = fopen($tmp, 'w');
        if (!$fh) {
            return false;
        }
        fwrite($fh, "SET NAMES 'utf8mb4';\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            fwrite($fh, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n");
            $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            if ($count === 0) {
                continue;
            }
            $offset = 0;
            $limit  = 1000;
            while ($offset < $count) {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
                if (!$rows) {
                    break;
                }
                $columns  = array_map(static function ($c) {
                    return '`' . $c . '`';
                }, array_keys($rows[0]));
                $col_list = implode(',', $columns);
                $values   = [];
                foreach ($rows as $r) {
                    $vals = array_map(function ($v) {
                        if (is_null($v)) {
                            return 'NULL';
                        }
                        return "'" . esc_sql($this->escape_sql_value($v)) . "'";
                    }, array_values($r));
                    $values[] = '(' . implode(',', $vals) . ')';
                }
                fwrite($fh, "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $values) . ";\n");
                $offset += $limit;
            }
        }
        fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        return $tmp;
    }

    /**
     * Costruisce la riga di comando per mysqldump.
     *
     * @param string $dest Percorso dove salvare il dump.
     * @return string|false
     */
    private function build_mysqldump_cmd($dest)
    {
        if (!function_exists('exec') || !defined('DB_NAME')) {
            return false;
        }
        $args = [];
        $args[] = 'mysqldump';
        $args[] = '--add-drop-table';
        $args[] = '--default-character-set=utf8mb4';
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
        $cmd = implode(' ', $args) . ' > ' . escapeshellarg($dest);
        return $cmd;
    }

    /**
     * Escape per i valori inseriti in SQL tramite fallback PHP.
     *
     * @param mixed $value
     * @return string
     */
    private function escape_sql_value($value)
    {
        // Gestisci booleani e numeri
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        // Escaping basilare per stringhe
        return str_replace(["\\", "'", "\0", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], $value);
    }
}