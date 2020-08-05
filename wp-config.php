<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'vtech' );

/** MySQL database username */
define( 'DB_USER', 'benny' );

/** MySQL database password */
define( 'DB_PASSWORD', 'Anhtrung2@' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'R7)b=RwQ4EM|o1#X!AyE,u%oKNG;4<3$g/rp%T3AyNw,R5gh&au&<rZEvxRZFa~{' );
define( 'SECURE_AUTH_KEY',  '.U.!v./>LAXU;?`xSjv7umiPI|Foc$Pei?v[X/}[azN u}1k|(-.3%3O2%l@gDFn' );
define( 'LOGGED_IN_KEY',    'qo3sF<#GC/`|Ag/Rw&Xb6PB=I9ZSWi8d(nZnK.>LVxoB60??6FoCu|*@gv-D5M,T' );
define( 'NONCE_KEY',        '/xw%dE{T1e.~A`rCr.@S(C3g:xqa|?=N,r].S8 L~@1ZxB&pcSqU bWPP g0Hlyu' );
define( 'AUTH_SALT',        'G7|vqI?7:09D>PLg~n0v+>~>.Vzkc 8=1 L={tvH?w!pB1amca9>@d.Sopu%yS_A' );
define( 'SECURE_AUTH_SALT', 'ctCU;M?@Af/,cuAzV?oaL?9,pf5_;dm?t?e0?zft4N!BkK.CG5l]6a%j5M9/z~lk' );
define( 'LOGGED_IN_SALT',   'gO*|)+UNu,`}V:A_!xMv924TSW5!*oOn#=wW^b84stgM}]7y87CDZ;RLO&RQ|r0R' );
define( 'NONCE_SALT',       'k|(h,5h<blndAE?y7?#$R[fuKYrz=!q?wp~qpv1QJ$4#Ah=.Ie}:v[{/@mjcKN~;' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_ewzv7y_';

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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
