<?php
/**
 * Plugin Name: Smart Product Filter for Elementor
 * Description: فیلتر هوشمند محصولات بر اساس دسته‌بندی و قیمت برای Elementor + WooCommerce
 * Version: 1.2.0
 * Author: Custom
 * Text Domain: spf
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SPF_VERSION', '1.2.0' );
define( 'SPF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPF_URL', plugin_dir_url( __FILE__ ) );

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
    $elements_manager->add_category(
        'spf-widgets',
        [
            'title' => 'فیلتر هوشمند',
            'icon'  => 'fa fa-filter',
        ]
    );
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

    wp_localize_script(
        'spf-script',
        'spfData',
        [
            'shopUrl' => function_exists( 'wc_get_page_permalink' )
                ? wc_get_page_permalink( 'shop' )
                : home_url( '/' ),
        ]
    );
}
