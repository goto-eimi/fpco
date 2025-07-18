<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dev-fpco' );

/** Database username */
define( 'DB_USER', 'dev-fpco' );

/** Database password */
define( 'DB_PASSWORD', 'kWV5LePcHGE4GPV' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'Os[uj`XlXE|h)ayi:*@BN^hxoO:[TthZVFuB %u6mv.@w?2KRdE]O4eUZ2AArXGO' );
define( 'SECURE_AUTH_KEY',  '-Oro|mNwMXEyS&f*23.v3,H+Ia46B>vsJWgam1&U}fiM+7{BeBj(]#?*Jy->IAwS' );
define( 'LOGGED_IN_KEY',    ';HCq4A_G+qHIeO@q[!s:$YgH2POF13cHWTo4/Mjnzb<R4PUDLiCpJ0(2,5{3Zjbc' );
define( 'NONCE_KEY',        '5GsV(A6hgran5tSIkPq!V:KrAS1.$kW%!I>aKMh29OvA),hqI];gv8&.9e5+c1Yd' );
define( 'AUTH_SALT',        '*W4*)_*F5|W^bOdXG-R$+.S85wAbe`4FARr#Ug6Mz0P^VyiTv@cj*e=L3|u_|<1,' );
define( 'SECURE_AUTH_SALT', 'XJvz.v63x=5(;l?rnCCbTSR]A`rRe5z%j=ibwB3SK&/NAztA;rQj>px`M1ON  bB' );
define( 'LOGGED_IN_SALT',   ':vqm~]8gdp#%.R,~jpypPHR{CKZ;>hen~Ub@:`yM?X`mFw8E{S,IiH,0TCcNEKs8' );
define( 'NONCE_SALT',       'ym,A6-sC)puWjhm^TYXr{<K*_c89XaA<Uf7Wg) wVL1hjo}E93#x$vC/JU@T$S+5' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

// DeprecatedとNoticeを非表示にする（強制）
@ini_set('display_errors', 0);
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);