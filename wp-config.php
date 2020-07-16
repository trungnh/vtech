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
define( 'AUTH_KEY',         ' hx;1a kGW09NlQqu)4/9ycp<ds,*`!>?*QRkid%MZ9~i~kWyo)-{)r[t8_g!s_v' );
define( 'SECURE_AUTH_KEY',  ',}WLJb>1M:TESB$,B+*_1I-[p2X|W#Z<Le]I152OcRckh#0U`9h4Z,zH|=s%A8t#' );
define( 'LOGGED_IN_KEY',    ']r:*amAnqoy]:-vE3Jm*{5hR}C}j1]{Sh<-Oc:KvllF#dP9u*$W~zF.AXKg-jU~%' );
define( 'NONCE_KEY',        'sh)?.=5w,l_:0~l.4xEDi0`IJg3lX@%D[b$f$B`=|1kHY>_9po%[y=p^#j6`iSSS' );
define( 'AUTH_SALT',        'Z[R[Q!>@WYqJ(!9~E?EjyAxa2>}E<,U<(){>J09PCr#rsN>/6O=HIY[w126)wL^L' );
define( 'SECURE_AUTH_SALT', '`,`{9gQtl-+@zuoGNw=@5B#kk$(Y7.fTn,L0okM~sBgU(}RtYE6&zZx+f=T5ZKT#' );
define( 'LOGGED_IN_SALT',   'h6Nj7ztLc5J1s9oo(w(C8)$_W>5oJ{Z(/eh+N|z A=c^[g-I1^HeR77-,UnGB^}S' );
define( 'NONCE_SALT',       'yP*K*yzO1~hfwzK4TGu*|FVK*SJ`=} p}wA103eMd`Ctba%7[D6@5MpNbLttAy`1' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
