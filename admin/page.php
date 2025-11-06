<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="wrap">
    <h1>Clona sito</h1>
    <?php if (!empty($_GET['wpcloner_status'])): ?>
        <?php if ($_GET['wpcloner_status'] === 'success'): ?>
            <div class="notice notice-success">
                <p>Import completato.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-error">
                <p>Si è verificato un errore durante l'import.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>


    <h2>Esporta</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wpcloner_export'); ?>
        <input type="hidden" name="action" value="wpcloner_export">
        <p>Genera un pacchetto ZIP con wp-content e il dump SQL.</p>
        <?php submit_button('Esporta pacchetto'); ?>
    </form>


    <hr />


    <h2>Importa</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcloner_import'); ?>
        <input type="hidden" name="action" value="wpcloner_import">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="wpcloner_package">Pacchetto .zip</label></th>
                <td><input type="file" name="wpcloner_package" id="wpcloner_package" accept=".zip" required></td>
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