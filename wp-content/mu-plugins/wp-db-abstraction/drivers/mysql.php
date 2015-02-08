<?php
/**
 * Mysql db class
 */

/**
 * Simple a wrapper for the original wpdb (wp-db.php) class which is always
 * included before db.php
 */
class mysql_wpdb extends wpdb {}
