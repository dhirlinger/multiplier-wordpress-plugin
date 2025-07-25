<?php
/*
* Plugin Name: Multiplier
* Description: Adds database tables and endpoints for use with the Multiplier React app and the Wordpress rest api for the purposes of saving user presets.
* Author: Doug Hirlinger
* Author URI: doughirlinger.com
*/

//Create database tables and seed one default Frequency Array

global $jal_db_version;
$jal_db_version = '1.0';

register_activation_hook(__FILE__, 'multiplier_setup_table');
register_activation_hook(__FILE__, 'multiplier_install_data');

function multiplier_setup_table()
{
    global $wpdb;
    global $jal_db_version;

    $index_array_table = $wpdb->prefix . 'multiplier_index_array';
    $freq_array_table  = $wpdb->prefix . 'multiplier_freq_array';
    $preset_table      = $wpdb->prefix . 'multiplier_preset';
    $user_table = $wpdb->prefix . 'users';
    $charset_collate = $wpdb->get_charset_collate();


    $sql = "
        CREATE TABLE $index_array_table (
        array_id mediumint(9) NOT NULL AUTO_INCREMENT,
        array_name VARCHAR(50) NOT NULL,
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
            name VARCHAR(25),
            tempo INT NOT NULL,
            waveshape VARCHAR(25) NOT NULL,
            duration DOUBLE NOT NULL,
            lowpass_freq INT NOT NULL,
            lowpass_q INT NOT NULL,
            index_array_id mediumint(9)  NOT NULL,
            freq_array_id mediumint(9)  NOT NULL,
            multiplier_min DOUBLE NOT NULL,
            multiplier_max DOUBLE NOT NULL,
            multiplier_step DOUBLE NOT NULL,
            base_min DOUBLE NOT NULL,
            base_max DOUBLE NOT NULL,
            base_step DOUBLE NOT NULL,
            user_id smallint(9) NOT NULL,
            PRIMARY KEY (preset_id),
            KEY  index_array_id (index_array_id),
            KEY  freq_array_id (freq_array_id),
            KEY  user_id (user_id)
        ) $charset_collate;
    ";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    //add foreign key contraints maunually

    $sql_fk_check =
        $wpdb->get_results(
            "SELECT array_id 
            FROM $freq_array_table
            WHERE array_id = 1;
            "
        );
    //avoid multiple contrain addition attemps upon more than one plugin activation
    if ($sql_fk_check == 0) {
        $wpdb->query("ALTER TABLE $preset_table 
            ADD CONSTRAINT fk_preset_index 
            FOREIGN KEY (index_array_id) REFERENCES $index_array_table(array_id);");

        $wpdb->query("ALTER TABLE $preset_table 
            ADD CONSTRAINT fk_preset_freq 
            FOREIGN KEY (freq_array_id) REFERENCES $freq_array_table(array_id);");
    }


    add_option('jal_db_version', $jal_db_version);
}

function multiplier_install_data()
{
    global $wpdb;

    $array_id = 1;
    $base_freq = 110;
    $multiplier = 2;
    $array_name = 'DEFAULT';
    $user_id = 1;

    $freq_array_table = $wpdb->prefix . 'multiplier_freq_array';

    $sql_fk_check =
        $wpdb->get_results(
            "SELECT array_id 
            FROM $freq_array_table
            WHERE array_id = 1;
            "
        );
    //avoid multiple insertions upon more than one plugin activation
    if ($sql_fk_check == 0) {
        $wpdb->insert(
            $freq_array_table,
            array(
                'array_id' => $array_id,
                'base_freq' => $base_freq,
                'multiplier' => $multiplier,
                'array_name' => $array_name,
                'user_id' => $user_id,
            )
        );
    }
}

/**
 * Register the REST API routes multiplier-api/v1/
 * freq-arrays 
 * index-arrays
 * presets
 */
add_action('rest_api_init', 'multiplier_routes');
function multiplier_routes()
{
    /*FREQ ARRAYS*/

    //get freq_arrays
    register_rest_route(
        'multiplier-api/v1',
        '/freq-arrays/',
        array(
            'methods'  => 'GET',
            'callback' => 'multiplier_get_freq_arrays',
            'permission_callback' => '__return_true'
        )
    );

    //post
    register_rest_route(
        'multiplier-api/v1',
        '/freq-arrays/',
        array(
            'methods'  => 'POST',
            'callback' => 'multiplier_create_freq_array',
            'permission_callback' => 'multiplier_check_permissions'
        )
    );
    //get freq_arrays for a user
    register_rest_route(
        'multiplier-api/v1',
        '/freq-arrays/(?P<id>\d+)',
        array(
            'methods'  => 'GET',
            'callback' => 'multiplier_get_freq_array',
            'permission_callback' => '__return_true'
        )
    );

    /*INDEX ARRAYS*/

    //post
    register_rest_route(
        'multiplier-api/v1',
        '/index-arrays/',
        array(
            'methods'  => 'POST',
            'callback' => 'multiplier_create_index_array',
            'permission_callback' => 'multiplier_check_permissions'
        )
    );
    //get index_arrays for a user
    register_rest_route(
        'multiplier-api/v1',
        '/index-arrays/(?P<id>\d+)',
        array(
            'methods'  => 'GET',
            'callback' => 'multiplier_get_index_array',
            'permission_callback' => '__return_true'
        )
    );

    /*PRESETS*/

    //post
    register_rest_route(
        'multiplier-api/v1',
        '/presets/',
        array(
            'methods'  => 'POST',
            'callback' => 'multiplier_create_preset',
            'permission_callback' => 'multiplier_check_permissions'
        )
    );
    //get presets for a user
    register_rest_route(
        'multiplier-api/v1',
        '/presets/(?P<id>\d+)',
        array(
            'methods'  => 'GET',
            'callback' => 'multiplier_get_presets',
            'permission_callback' => '__return_true'
        )
    );
}

/**
 * GET all callback for the multiplier-api/v1/freq-arrays route
 *
 * @return array|object|stdClass[]|null
 */

//GET callback for all arrays
function multiplier_get_freq_arrays()
{

    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_freq_array';

    $results = $wpdb->get_results("SELECT * FROM $table_name");

    return $results;
}

//post callback
function multiplier_create_freq_array($request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_freq_array';

    $rows = $wpdb->insert(
        $table_name,
        array(
            'base_freq' => $request['base_freq'],
            'multiplier' => $request['multiplier'],
            'array_name' => $request['array_name'],
            'user_id' => $request['user_id'],
        )
    );

    return $rows;
}
//GET callback for a single user
function multiplier_get_freq_array($request)
{
    $id = $request['id'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_freq_array';

    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = $id");

    return $results;
}

/**
 * GET all callback for the multiplier-api/v1/index-arrays route
 *
 */

//post callback
function multiplier_create_index_array($request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_index_array';

    $rows = $wpdb->insert(
        $table_name,
        array(
            'index_array' => $request['index_array'],
            'array_name' => $request['array_name'],
            'user_id' => $request['user_id'],
        )
    );

    return $rows;
}
//GET callback for a single user
function multiplier_get_index_array($request)
{
    $id = $request['id'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_index_array';

    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = $id");

    return $results;
}

/**
 * GET all callback for the multiplier-api/v1/presets route
 *
 */

//post callback
function multiplier_create_preset($request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_preset';

    $rows = $wpdb->insert(
        $table_name,
        array(
            'name' => $request['name'],
            'tempo' => $request['tempo'],
            'waveshape' => $request['waveshape'],
            'duration' => $request['duration'],
            'lowpass_freq' => $request['lowpass_freq'],
            'lowpass_q' => $request['lowpass_q'],
            'index_array_id' => $request['index_array_id'],
            'freq_array_id' => $request['freq_array_id'],
            'multiplier_min' => $request['multiplier_min'],
            'multiplier_max' => $request['multiplier_max'],
            'multiplier_step' => $request['multiplier_step'],
            'base_min' => $request['base_min'],
            'base_max' => $request['base_max'],
            'base_step' => $request['base_step'],
            'user_id' => $request['user_id'],
        )
    );

    return $rows;
}
//GET callback for a single user
function multiplier_get_presets($request)
{
    $id = $request['id'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'multiplier_preset';

    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = $id");

    return $results;
}

function multiplier_check_permissions()
{
    return current_user_can('edit_posts');
}
