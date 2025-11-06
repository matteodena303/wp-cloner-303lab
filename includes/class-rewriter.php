<?php
if (!defined('ABSPATH')) {
    exit;
}


class WPCloner_Rewriter
{
    /**
     * Esegue search & replace sicuro su DB, preservando dati serializzati.
     * - $map: [ old => new, ... ]
     */
    public function run(array $map)
    {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        foreach ($tables as $table) {
            $pk = $this->guess_primary_key($table);
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
            if (!$columns) continue;
            $text_cols = array_filter($columns, function ($c) {
                return preg_match('/text|char|blob|json/i', $c['Type']);
            });
            if (empty($text_cols)) continue;


            $idcol = $pk ?: $columns[0]['Field'];
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            foreach ($rows as $row) {
                $changed = false;
                $updated = $row;
                foreach ($text_cols as $c) {
                    $field = $c['Field'];
                    $val = $row[$field];
                    if ($val === null || $val === '') continue;
                    $newval = $this->safereplace($val, $map);
                    if ($newval !== $val) {
                        $updated[$field] = $newval;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $this->update_row($table, $updated, $idcol, $row[$idcol]);
                }
            }
        }
    }


    private function safereplace($value, array $map)
    {
        // Se è serializzato, deserializza e lavora ricorsivamente
        if ($this->is_serialized($value)) {
            $data = @unserialize($value);
            if ($data !== false || $value === 'b:0;') {
                $data = $this->walk_replace($data, $map);
                return serialize($data);
            }
        }
        // JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $decoded = $this->walk_replace($decoded, $map);
            return wp_json_encode($decoded);
        }
        // Stringa semplice
        foreach ($map as $old => $new) {
            $value = str_replace($old, $new, $value);
        }
        return $value;
    }


    private function walk_replace($data, array $map)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->walk_replace($v, $map);
            }
        } elseif (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = $this->walk_replace($v, $map);
            }
        } elseif (is_string($data)) {
            foreach ($map as $old => $new) {
                $data = str_replace($old, $new, $data);
            }
        }
        return $data;
    }


    private function is_serialized($value)
    {
        return is_string($value) && preg_match('/^(?:a|O|s|b|i|d|N)\:/', $value) && preg_match('/;$/', $value);
    }


    private function guess_primary_key($table)
    {
        global $wpdb;
        $keys = $wpdb->get_results("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'", ARRAY_A);
        return $keys && isset($keys[0]['Column_name']) ? $keys[0]['Column_name'] : null;
    }


    private function update_row($table, $data, $idcol, $idval)
    {
        global $wpdb;
        $formats = [];
        foreach ($data as $k => $v) {
            $formats[] = is_numeric($v) ? '%d' : '%s';
        }
        $wpdb->update($table, $data, [$idcol => $idval], $formats, [is_numeric($idval) ? '%d' : '%s']);
    }
}
