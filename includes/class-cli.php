<?php
if (!defined('ABSPATH')) {
    exit;
}

// Registra il comando solo se WP-CLI è disponibile.
if (defined('WP_CLI') && WP_CLI) {
    /**
     * Comando WP-CLI per WP Cloner.
     *
     * Offre le subcommands `export` e `import` per eseguire le operazioni via CLI.
     */
    class WPCloner_CLI
    {
        /**
         * Esporta il sito in un pacchetto zip.
         *
         * ## OPTIONS
         * [--to=<file>] Percorso dove salvare il pacchetto.
         * [--compression=<zip|zipgz>] Formato di compressione (default: zip).
         */
        public function export($args, $assoc_args)
        {
            $exporter = new WPCloner_Exporter();
            $manifest = $exporter->build_manifest();
            $dump     = $exporter->dump_database();
            if (!$dump) {
                WP_CLI::error('Dump fallito');
            }
            $to   = $assoc_args['to'] ?? (WP_CONTENT_DIR . '/uploads/wpcloner/export-' . date('Ymd-His') . '.zip');
            $comp = $assoc_args['compression'] ?? 'zip';
            $packager = new WPCloner_Packager();
            $ok   = $packager->zip($to, [WP_CONTENT_DIR, $dump], $manifest);
            if (file_exists($dump)) {
                @unlink($dump);
            }
            if (!$ok) {
                WP_CLI::error('Creazione pacchetto fallita');
            }
            // Gzip opzionale
            if ($comp === 'zipgz') {
                $gz_path = $to . '.gz';
                $data    = file_get_contents($to);
                $gzdata  = gzencode($data, 9);
                file_put_contents($gz_path, $gzdata);
                @unlink($to);
                $to = $gz_path;
            }
            WP_CLI::success('Pacchetto creato: ' . $to);
        }

        /**
         * Importa un pacchetto zip nella installazione corrente.
         *
         * ## OPTIONS
         * --from=<file> Il file da importare.
         * [--new_url=<url>] Nuovo URL del sito.
         */
        public function import($args, $assoc_args)
        {
            $from = $assoc_args['from'] ?? null;
            if (!$from || !file_exists($from)) {
                WP_CLI::error('Pacchetto non trovato');
            }
            $importer = new WPCloner_Importer();
            $ok = $importer->import_package($from, [
                'new_url' => $assoc_args['new_url'] ?? '',
            ]);
            if (!$ok) {
                WP_CLI::error('Import fallito');
            }
            WP_CLI::success('Import completato');
        }
    }
}