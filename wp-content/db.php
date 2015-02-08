<?php
/*
Plugin Name: WP Db Abstraction
Plugin URI:  http://wordpress.org/extend/plugins/wordpress-database-abstraction/
Description: Database class drop in override file for WP Db Abstraction plugin
Version: 1.1.4
Author: Anthony Gentile and Elizabeth M Smith
Author URI:  http://wordpress.visitmix.com/
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 * Function to "namespace" our basic "are we installed properly" checks
 * to avoid polluting global namespace
 */
function wp_db_abstraction_check_db_install() {
    /**
     * Check to make sure that wp-db-abstraction is in mu-plugins folder
     */
    $wp_db_ab_plugin_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mu-plugins'
        . DIRECTORY_SEPARATOR . 'wp-db-abstraction' . DIRECTORY_SEPARATOR;
    if (!is_dir($wp_db_ab_plugin_path) && !file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wp-db-abstraction.php')) {
        echo '<h1>ERROR: wp-db-abstraction plugin not installed correctly in mu-plugins directory!</h1>';
        die;
    }
    
    /**
     * Check to make sure that this is in wp-content directory
     */
    if (basename(dirname(__FILE__)) !== 'wp-content') {
        if (!copy(__FILE__, $wp_db_ab_plugin_path . 'db.php') ) {
            $error_message = 'WP Db Abstraction requires db.php to be in the wp-content/ directory.';
            include $wp_db_ab_plugin_path . 'error_page.php';
            die;
        }
    }
    
    /**
     * Check to see if the page we're on is called admin/setup-config.php
     * If it is, redirect to OUR setup-config.php
     */
    $strip_query = explode('?', $_SERVER['PHP_SELF'], 2);
    if (preg_match('!wp-admin/setup-config.php$!', $strip_query[0]) === 1) {
        $url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . str_replace('wp-admin/setup-config.php', 'wp-content/mu-plugins/wp-db-abstraction/setup-config.php', $_SERVER['PHP_SELF']);
        $content = <<<EOD
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html>
  <head>
    <title>302 Found</title>
  </head>
<body>
  <h1>Found</h1>
  <p>The document has moved <a href="$url">here</a>.</p>
  </body>
</html>

EOD;
        header('HTTP/1.1 302 Moved');
        header('Content-Type: text/html');
        header('Location: ' . $url);
        echo $content;
        exit;
    }
}

/**
 * Function to create a new database connection using our abstraction layer
 * The default for calling this occurs with the DB_* constants and
 * the value becomes the global $wpdb variable, but you can call this
 * again with your own values to get an additional db instance
 *
 * @returns Database class instance
 */
function wp_db_abstraction_create_wpdb ($type, $user, $password, $name, $host) {

    if (stristr($type, 'pdo_') !== FALSE) {
        $pdo_type = str_replace('pdo_', '', $type);
        $type = 'pdo';
    }

    require_once(dirname(__FILE__) . 
            DIRECTORY_SEPARATOR . 'mu-plugins' .
            DIRECTORY_SEPARATOR . 'wp-db-abstraction' .
            DIRECTORY_SEPARATOR . 'drivers' . 
            DIRECTORY_SEPARATOR . $type . '.php');

    $class = $type . '_wpdb';

    if (isset($pdo_type)) {
        return new $class($user, $password, $name, $host, $pdo_type);
    }
    return new $class($user, $password, $name, $host);
}

/* Actually check our install location and create our global database object */
wp_db_abstraction_check_db_install();

// BC for old config.php files
if ( !defined('DB_TYPE') ) {
    define('DB_TYPE', 'mysql');
}
// create our global wpdb with config settings
$wpdb = wp_db_abstraction_create_wpdb(DB_TYPE, DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

/*  ADD ANY ADDITIONAL INCLUDES HERE */
// include 'db-plugin.php';