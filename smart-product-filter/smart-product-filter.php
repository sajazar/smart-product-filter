<?php
/**
 * Plugin Name: Smart Product Filter for Elementor
 * Description: فیلتر هوشمند محصولات برای Elementor + WooCommerce + WoodMart
 * Version: 1.6.1
 * Author: Custom
 * Text Domain: spf
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SPF_VERSION', '1.6.1' );
define( 'SPF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPF_URL', plugin_dir_url( __FILE__ ) );

/**
 * Ensure AJAX handler is loaded when plugins are ready (so WooCommerce classes exist).
 */
add_action( 'plugins_loaded', 'spf_load_dependencies', 20 );
function spf_load_dependencies() {
    if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
        require_once SPF_PATH . 'includes/class-ajax.php';
    }
}

add_action( 'elementor/widgets/register', 'spf_register_widget' );
function spf_register_widget( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    require_once SPF_PATH . 'includes/class-widget.php';

    if ( class_exists( '\SPF\Widget\Smart_Filter' ) ) {
        $widgets_manager->register( new \SPF\Widget\Smart_Filter() );
    }
}

add_action( 'elementor/elements/categories_registered', 'spf_register_category' );
function spf_register_category( $elements_manager ) {
    $elements_manager->add_category( 'spf-widgets', [
        'title' => 'فیلتر هوشمند',
        'icon'  => 'fa fa-filter',
    ] );
}

add_action( 'wp_enqueue_scripts', 'spf_register_assets' );
function spf_register_assets() {
    wp_register_style(
        'spf-style',
        SPF_URL . 'assets/css/style.css',
        [],
        SPF_VERSION
    );

    wp_register_script(
        'spf-script',
        SPF_URL . 'assets/js/script.js',
        [ 'jquery' ],
        SPF_VERSION,
        true
    );

    // Localize essential data for the frontend script (safe if script not enqueued)
    $data = [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'spf_nonce' ),
        'i18n'    => [ 'error' => __( 'بارگذاری محصولات انجام نشد.', 'spf' ) ],
    ];
    wp_localize_script( 'spf-script', 'SPF_DATA', $data );
}
