<?php
if (!defined('ABSPATH')) {
    exit;
}


if (defined('WP_CLI') && WP_CLI) {
    class WPCloner_CLI
    {
        /**
         * Export site into a zip package.
         *
         * ## OPTIONS
         * [--to=<file>]
         */
        public function export($args, $assoc_args)
        {
            $exporter = new WPCloner_Exporter();
            $manifest = $exporter->build_manifest();
            $dump = $exporter->dump_database();
            if (!$dump) WP_CLI::error('Dump fallito');


            $to = $assoc_args['to'] ?? (WP_CONTENT_DIR . '/uploads/wpcloner/export-' . date('Ymd-His') . '.zip');
            $packager = new WPCloner_Packager();
            $ok = $packager->zip($to, [WP_CONTENT_DIR, $dump], $manifest);
            if (file_exists($dump)) @unlink($dump);
            if (!$ok) WP_CLI::error('Creazione pacchetto fallita');
            WP_CLI::success('Pacchetto creato: ' . $to);
        }


        /**
         * Import a zip package into current site.
         *
         * ## OPTIONS
         * --from=<file>
         * [--new_url=<url>]
         */
        public function import($args, $assoc_args)
        {
            $from = $assoc_args['from'] ?? null;
            if (!$from || !file_exists($from)) WP_CLI::error('Pacchetto non trovato');
            $importer = new WPCloner_Importer();
            $ok = $importer->import_package($from, [
                'new_url' => $assoc_args['new_url'] ?? '',
            ]);
            if (!$ok) WP_CLI::error('Import fallito');
            WP_CLI::success('Import completato');
        }
    }
}
