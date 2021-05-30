<div class="wrap">
    <h1>Custom config for themes </h1>
    <h5><?php echo get_admin_page_title() ?></h5>
    <form action="options.php" method="POST">
        <?php
        settings_fields( 'option_group_addition_config_wps' );
        do_settings_sections( 'wps_config_page' );
        submit_button();
        ?>
    </form>
</div>