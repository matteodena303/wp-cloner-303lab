<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="wrap">
    <h1>Clona sito</h1>
    <?php
    // Mostra notifiche post-import.
    if (!empty($_GET['wpcloner_status'])) {
        if ($_GET['wpcloner_status'] === 'success') {
            echo '<div class="notice notice-success"><p>Import completato.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Si è verificato un errore durante l\'import.</p></div>';
        }
    }

    // Se è presente un job in corso, mostra la barra di progresso e termina qui.
    if (!empty($_GET['wpcloner_job'])) {
        $job_id = sanitize_text_field($_GET['wpcloner_job']);
        ?>
        <h2>Esportazione in corso</h2>
        <div id="wpcloner-progress-bar" style="width:100%;background:#e5e5e5;height:20px;margin-bottom:5px;">
            <div id="wpcloner-progress-inner" style="width:0%;height:100%;background:#0073aa;"></div>
        </div>
        <p id="wpcloner-progress-text">0%</p>
        <script>
        (function($){
            function poll(){
                $.get(ajaxurl, { action: 'wpcloner_progress', job_id: '<?php echo esc_js($job_id); ?>' }, function(resp){
                    if(resp.success){
                        var pct = parseInt(resp.data.progress, 10);
                        $('#wpcloner-progress-inner').css('width', pct + '%');
                        $('#wpcloner-progress-text').text(pct + '%');
                        if(resp.data.status === 'complete'){
                            // quando completo, mostra link download
                            $('#wpcloner-progress-text').after('<p><a class="button button-primary" href="'+ajaxurl+'?action=wpcloner_download&job_id=<?php echo esc_js($job_id); ?>">Scarica pacchetto</a></p>');
                        } else if(resp.data.status === 'error'){
                            $('#wpcloner-progress-text').after('<p class="error">Si è verificato un errore durante l\'esportazione.</p>');
                        } else {
                            setTimeout(poll, 2000);
                        }
                    }
                });
            }
            $(document).ready(function(){ setTimeout(poll, 1500); });
        })(jQuery);
        </script>
        <?php
        echo '</div>';
        return;
    }
    ?>

    <h2>Esporta</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wpcloner_export'); ?>
        <input type="hidden" name="action" value="wpcloner_export">
        <p>Genera un pacchetto con wp-content e il dump SQL.</p>
        <p>
            <label for="compression">Formato di compressione:</label>
            <select name="compression" id="compression">
                <option value="zip">ZIP (rapido)</option>
                <option value="zipgz">ZIP + GZIP (più compatto)</option>
            </select>
        </p>
        <?php submit_button('Avvia esportazione'); ?>
    </form>

    <hr />

    <h2>Importa</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcloner_import'); ?>
        <input type="hidden" name="action" value="wpcloner_import">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="wpcloner_package">Pacchetto (.zip o .zip.gz)</label></th>
                <td><input type="file" name="wpcloner_package" id="wpcloner_package" accept=".zip,.gz" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="new_url">Nuovo URL</label></th>
                <td><input type="url" name="new_url" id="new_url" class="regular-text" placeholder="https://nuovosito.test"></td>
            </tr>
            <tr>
                <th scope="row">Opzioni</th>
                <td>
                    <label><input type="checkbox" name="strip_emails" value="1"> Anonimizza email utenti (staging)</label><br>
                    <label><input type="checkbox" name="disable_mail" value="1"> Disabilita invio email (staging)</label>
                </td>
            </tr>
        </table>
        <?php submit_button('Importa pacchetto'); ?>
    </form>
</div>