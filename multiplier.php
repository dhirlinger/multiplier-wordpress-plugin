<?php
/*
* Plugin Name: Multiplier
* Description: Adds database tables and endpoints for use with the Multiplier React app and the Wordpress rest api for the purposes of saving user presets.
* Author: Doug Hirlinger
* Author URI: doughirlinger.com
*/

//Create database tables and seed one default Frequency Array

register_activation_hook(__FILE__, 'multiplier_setup_table');
function multiplier_setup_table()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $index_array_table = $wpdb->prefix . 'multiplier_index_array';
    $freq_array_table  = $wpdb->prefix . 'multiplier_freq_array';
    $preset_table      = $wpdb->prefix . 'multiplier_preset';

    $sql = "
CREATE TABLE $index_array_table (
    array_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    index_array VARCHAR(25) NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (array_id),
    KEY user_id (user_id)
) $charset_collate;

CREATE TABLE $freq_array_table (
    array_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    array_name VARCHAR(50) NOT NULL,
    base_freq DOUBLE NOT NULL,
    multiplier DOUBLE NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (array_id),
    KEY user_id (user_id)
) $charset_collate;

CREATE TABLE $preset_table (
    preset_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    waveshape VARCHAR(25) NOT NULL,
    duration DOUBLE NOT NULL,
    lowpass_freq INT NOT NULL,
    lowpass_q INT NOT NULL,
    index_array_id BIGINT(20) UNSIGNED NOT NULL,
    freq_array_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (preset_id),
    KEY index_array_id (index_array_id),
    KEY freq_array_id (freq_array_id),
    KEY user_id (user_id)
) $charset_collate;
";

    dbDelta($sql);

    // Add foreign key constraints manually
    $wpdb->query("ALTER TABLE $index_array_table 
    ADD CONSTRAINT fk_index_user 
    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID);");

    $wpdb->query("ALTER TABLE $freq_array_table 
    ADD CONSTRAINT fk_freq_user 
    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID);");

    $wpdb->query("ALTER TABLE $preset_table 
    ADD CONSTRAINT fk_preset_index 
    FOREIGN KEY (index_array_id) REFERENCES $index_array_table(array_id);");

    $wpdb->query("ALTER TABLE $preset_table 
    ADD CONSTRAINT fk_preset_freq 
    FOREIGN KEY (freq_array_id) REFERENCES $freq_array_table(array_id);");

    $wpdb->query("ALTER TABLE $preset_table 
    ADD CONSTRAINT fk_preset_user 
    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID);");

    //seed Freq Array with a default row
    $wpdb->insert($freq_array_table, [
        'array_name' => 'DEFAULT',
        'base_freq' => 110.0,
        'multiplier' => 2.0,
        'user_id' => 1
    ]);
}

/**
 * Register the REST API multiplier-api/v1/freq-arrays route
 */
add_action('rest_api_init', 'multiplier_freq_arrays_routes');
function multiplier_freq_arrays_routes()
{
    register_rest_route(
        'multiplier-api/v1',
        '/freq-arrays/',
        array(
            'methods'  => 'GET',
            'callback' => 'multiplier_get_freq_arrays',
            'permission_callback' => '__return_true'
        )
    );
}

/**
 * GET callback for the multiplier-api/v1/freq-arrays route
 *
 * @return array|object|stdClass[]|null
 */
function multiplier_get_freq_arrays()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_freq-array';

    $results = $wpdb->get_results("SELECT * FROM $table_name");

    return $results;
}
