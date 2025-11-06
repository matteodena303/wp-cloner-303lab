<?php
if (!defined('ABSPATH')) {
    exit;
}


class WPCloner_Packager
{
    public function zip($zip_path, array $include_paths, array $manifest)
    {
        if (!class_exists('ZipArchive')) return false;
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;


        // Aggiungi manifest
        $zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT));


        foreach ($include_paths as $path) {
            if (!file_exists($path)) continue;
            $this->addPath($zip, $path);
        }


        return $zip->close();
    }


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


    public function unzip($zip_file, $dest_dir)
    {
        if (!class_exists('ZipArchive')) return false;
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) return false;
        $zip->extractTo($dest_dir);
        $zip->close();
        return true;
    }
}
