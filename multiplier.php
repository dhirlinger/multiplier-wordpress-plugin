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
    $freq_array = $wpdb->prefix . 'freq_array';
    $index_array = $wpdb->prefix . 'index_array';
    $preset = $wpdb->prefix . 'preset';

    $sql = "
        CREATE TABLE $freq_array (
            array_id INTEGER PRIMARY KEY AUTO_INCREMENT, 
            array_name VARCHAR(50),
            base_freq DOUBLE,
            multiplier DOUBLE,
            user_id INTEGER, 
            FOREIGN KEY (user_id) REFERENCES wp_users(ID)
        );

        CREATE TABLE $index_array (
	        array_id INTEGER PRIMARY KEY AUTO_INCREMENT, 
            index_array VARCHAR(25),
            user_id INTEGER, 
            FOREIGN KEY (user_id) REFERENCES wp_users(ID)
        );

        CREATE TABLE $preset (
	        preset_id INTEGER PRIMARY KEY AUTO_INCREMENT, 
            waveshape VARCHAR(25),
            duration DOUBLE,
            lowpass_freq INTEGER,
            lowpass_q INTEGER,
	        index_array_id INTEGER,
             FOREIGN KEY (index_array_id) REFERENCES index_array(array_id),
            freq_array_id INTEGER,
            FOREIGN KEY (freq_array_id) REFERENCES freq_array(array_id),
            user_id INTEGER, 
            FOREIGN KEY (user_id) REFERENCES wp_users(ID)
        );

        INSERT INTO freq_array (array_name, base_freq, multiplier, user_id)
        VALUES ('DEFAULT', 110.0, 2.0, 1);
        
        ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
