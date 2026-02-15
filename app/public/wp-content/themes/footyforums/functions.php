<?php
/**
 * FootyForums Theme — functions.php
 *
 * Bootstrap: autoload includes, enqueue assets, register widget areas,
 * flush rewrite rules on theme activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FF_THEME_VERSION', '1.0.0' );
define( 'FF_THEME_DIR', get_template_directory() );
define( 'FF_THEME_URI', get_template_directory_uri() );

/* ------------------------------------------------------------------
 * Autoload includes
 * ----------------------------------------------------------------*/

require_once FF_THEME_DIR . '/inc/class-ff-auth.php';
require_once FF_THEME_DIR . '/inc/class-ff-router.php';
require_once FF_THEME_DIR . '/inc/class-ff-data.php';
require_once FF_THEME_DIR . '/inc/class-ff-widgets.php';
require_once FF_THEME_DIR . '/inc/class-ff-admin.php';

/* ------------------------------------------------------------------
 * Initialise modules
 * ----------------------------------------------------------------*/

FF_Auth::init();
FF_Router::init();
FF_Widgets::init();
FF_Admin::init();

/* ------------------------------------------------------------------
 * Theme support
 * ----------------------------------------------------------------*/

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
} );

/* ------------------------------------------------------------------
 * Enqueue styles & scripts
 * ----------------------------------------------------------------*/

add_action( 'wp_enqueue_scripts', function () {
    // Google Fonts — Inter
    wp_enqueue_style(
        'ff-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        array(),
        null
    );

    // Flag Icons
    wp_enqueue_style(
        'ff-flag-icons',
        'https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.3.2/css/flag-icons.min.css',
        array(),
        '7.3.2'
    );

    // CSS files in load order
    $css_files = array(
        'variables',
        'base',
        'layout',
        'header',
        'cards',
        'tabs',
        'standings',
        'fixtures',
        'stats',
        'widgets',
        'responsive',
    );

    foreach ( $css_files as $file ) {
        wp_enqueue_style(
            'ff-' . $file,
            FF_THEME_URI . '/assets/css/' . $file . '.css',
            array( 'ff-google-fonts' ),
            FF_THEME_VERSION
        );
    }

    // Theme toggle JS
    wp_enqueue_script(
        'ff-theme-toggle',
        FF_THEME_URI . '/assets/js/theme-toggle.js',
        array(),
        FF_THEME_VERSION,
        true
    );

    // Accordion JS
    wp_enqueue_script(
        'ff-accordion',
        FF_THEME_URI . '/assets/js/accordion.js',
        array(),
        FF_THEME_VERSION,
        true
    );
} );
