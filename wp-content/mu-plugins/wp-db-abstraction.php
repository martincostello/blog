<?php
/*
Plugin Name: WP Db Abstraction
Plugin URI:  http://wordpress.org/extend/plugins/wordpress-database-abstraction/
Description: Data-Access abstraction and SQL abstraction support for Wordpress. MU (must use) plugin ONLY, do not install as a regular plugin. Please install and upgrade manually, see plugin site for details.  Also requires db.php drop-in. Currently supports PDO, sqlsrv, mssql and mysql database extensions and sql abstraction for SQL Server.
Version: 1.1.4
Author: Anthony Gentile and Elizabeth M Smith
Author URI: http://wordpress.visitmix.com/
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

if (!function_exists('wp_db_abstraction_check_plugin_install')) {

    /**
     * Function to "namespace" our basic "are we installed properly" checks
     * to avoid polluting global namespace
     */
    function wp_db_abstraction_check_plugin_install() {

        /**
         * Check to make sure that this file is in the mu-plugins folder
         */
        $wp_db_ab_plugin_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wp-db-abstraction' . DIRECTORY_SEPARATOR;
        if (basename(dirname(__FILE__)) !== 'mu-plugins') {
            $error_message = 'WP Db Abstraction can only be installed to the mu-plugins directory in wp-content/';
            include $wp_db_ab_plugin_path . 'error_page.php';
            die;
        }
        
        /**
         * Check to make sure that db.php is up from here in the wp-content folder
         */
        $wp_db_ab_db_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'db.php';
        if (!file_exists($wp_db_ab_db_file)) {
            if (!copy($wp_db_ab_plugin_path . 'db.php', $wp_db_ab_db_file)) {
                $error_message = 'WP Db Abstraction requires db.php to be in the wp-content/ directory.';
                include $wp_db_ab_plugin_path . 'error_page.php';
                die;
            }
        }
    }

    wp_db_abstraction_check_plugin_install();
} else {
    $wp_db_ab_plugin_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'wp-db-abstraction' . DIRECTORY_SEPARATOR;
    $error_message = 'WP Db Abstraction is already loaded and cannot be loaded twice';
    include $wp_db_ab_plugin_path . 'error_page.php';
    die;
}