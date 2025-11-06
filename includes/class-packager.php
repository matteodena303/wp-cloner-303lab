<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe che si occupa della creazione e dell'estrazione dei pacchetti zip.
 */
class WPCloner_Packager
{
    /**
     * Crea un archivio ZIP con i percorsi specificati e include il manifest.
     *
     * @param string $zip_path Percorso del file zip da creare.
     * @param array  $include_paths Elenco di percorsi (file o directory) da includere.
     * @param array  $manifest Dati del manifest da serializzare in JSON.
     *
     * @return bool True se l'archivio è stato creato correttamente, false altrimenti.
     */
    public function zip($zip_path, array $include_paths, array $manifest)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        // Aggiungi il manifest come file JSON.
        $zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT));
        // Aggiungi ciascun percorso all'archivio.
        foreach ($include_paths as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $this->addPath($zip, $path);
        }
        return $zip->close();
    }

    /**
     * Aggiunge ricorsivamente un percorso all'archivio zip.
     *
     * @param ZipArchive $zip
     * @param string     $path
     * @param string     $base
     * @return void
     */
    private function addPath(ZipArchive $zip, $path, $base = '')
    {
        $path = wp_normalize_path($path);
        if (is_dir($path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $local = trim($base . '/' . ltrim(str_replace(ABSPATH, '', wp_normalize_path($file->getPathname())), '/'), '/');
                if ($file->isDir()) {
                    $zip->addEmptyDir($local);
                } else {
                    $zip->addFile($file->getPathname(), $local);
                }
            }
        } else {
            $local = trim($base . '/' . ltrim(str_replace(ABSPATH, '', $path), '/'), '/');
            $zip->addFile($path, $local);
        }
    }

    /**
     * Estrae un archivio ZIP in una directory di destinazione.
     *
     * @param string $zip_file
     * @param string $dest_dir
     * @return bool
     */
    public function unzip($zip_file, $dest_dir)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            return false;
        }
        $zip->extractTo($dest_dir);
        $zip->close();
        return true;
    }
}