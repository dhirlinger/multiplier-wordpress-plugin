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

    $index_array_table = $wpdb->prefix . 'multiplier_index_array';
    $freq_array_table  = $wpdb->prefix . 'multiplier_freq_array';
    $preset_table      = $wpdb->prefix . 'multiplier_preset';
    $user_table = $wpdb->prefix . 'users';
    $charset_collate = $wpdb->get_charset_collate();


    $sql = "
        CREATE TABLE $index_array_table (
        array_id mediumint(9) NOT NULL AUTO_INCREMENT,
        index_array VARCHAR(25) NOT NULL,
        user_id smallint(9) NOT NULL,
        PRIMARY KEY  (array_id),
        KEY  (user_id) 
        ) $charset_collate;

        CREATE TABLE $freq_array_table (
            array_id mediumint(9) NOT NULL AUTO_INCREMENT,
            array_name VARCHAR(50) NOT NULL,
            base_freq DOUBLE,
            multiplier DOUBLE,
            user_id smallint(9) NOT NULL,
            PRIMARY KEY  (array_id),
            KEY  (user_id)
        ) $charset_collate;

        CREATE TABLE $preset_table (
            preset_id mediumint(9) NOT NULL AUTO_INCREMENT,
            waveshape VARCHAR(25) NOT NULL,
            duration DOUBLE NOT NULL,
            lowpass_freq INT NOT NULL,
            lowpass_q INT NOT NULL,
            index_array_id mediumint(9)  NOT NULL,
            freq_array_id mediumint(9)  NOT NULL,
            user_id smallint(9) NOT NULL,
            PRIMARY KEY (preset_id),
            KEY  index_array_id (index_array_id),
            KEY  freq_array_id (freq_array_id),
            KEY  user_id (user_id)
        ) $charset_collate;
    ";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
