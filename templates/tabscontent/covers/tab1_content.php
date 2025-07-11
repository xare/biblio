<form method="post" action="options.php">
    <?php
        settings_fields( 'covers_settings' );
        do_settings_sections( 'covers' );
        submit_button();
    ?>
</form>