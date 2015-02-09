<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

/** MySQL */
/**
define('DB_NAME', 'martincARcOLsU3E');
define('DB_USER', 'b5fb59b7f9b92a');
define('DB_PASSWORD', '6d3ffb93');
define('DB_HOST', 'eu-cdbr-azure-north-b.cloudapp.net');
*/

/** SQL Server */
define('DB_NAME', 'wordpress');
define('DB_USER', 'wordpress_user@vuhhxypltc');
define('DB_PASSWORD', '?Oe8QeqKj2UWVWS');
define('DB_HOST', 'vuhhxypltc.database.windows.net,1433');
define('DB_TYPE', 'sqlsrv');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'b|r~ABM/7%s0[b:Y@^Re]NRsqX2+G`1tU,oJ4f!X7i5C%gPh(SUt1bIx$;54NEzq');
define('SECURE_AUTH_KEY',  '~%]s=j(!wc.h>CLLvt.p pp3}Elu>T!$Nl>%tE=B)BOd7!qW!}gALINOC-zhS>!T');
define('LOGGED_IN_KEY',    'u,bsKtWR!)^x*iI[zB^];%@0/c+-D:te>TLtr_YA[>`LS3Nh(UTrs2yd#W0LENow');
define('NONCE_KEY',        '5RM1h/=;S~zgd)lChFQd+A 8.W9-@Cd*A/IPB(qo/5B^An|g4ntSwj^0vPX>N*I*');
define('AUTH_SALT',        'M |y~A [g>J 1pDA2~K3?XC:GvQ+:)9M1}M)#DI-ZHG!!Y__[i3lT_zOhAr-N`j5');
define('SECURE_AUTH_SALT', '=r%$4(gu;5=$EBN4lf3sG!e5I=pL~8eP$$Fy/ c:6%DW#L[6;<8%!^Xxve*l{B.(');
define('LOGGED_IN_SALT',   'JVS|!tFB2}Z&8k2-WkZ_dh~yP;UlQ{iaDvoqt_V j%q+7 {DSkT{AZs;-;P.-Y:Q');
define('NONCE_SALT',       '-XCc=Q}@D|BQK1_J>n.[&!K.;&LBM=K-JFrL|-1rtJ FTBonsqrbm[Bdw-*3KrY>');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/** Query Logging Settings */
define('SAVEQUERIES', FALSE);
define('QUERY_LOG', 'D:\home\LogFiles\queries.log');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
