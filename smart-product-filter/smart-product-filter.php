<?php
/**
 * Plugin Name: Smart Product Filter for Elementor
 * Description: فیلتر هوشمند محصولات برای Elementor + WooCommerce + WoodMart
 * Version: 1.4.0
 * Author: Custom
 * Text Domain: spf
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SPF_VERSION', '1.4.0' );
define( 'SPF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPF_URL', plugin_dir_url( __FILE__ ) );

require_once SPF_PATH . 'includes/class-ajax.php';

add_action( 'elementor/widgets/register', 'spf_register_widget' );
function spf_register_widget( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;
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
    wp_register_style( 'spf-style', SPF_URL . 'assets/css/style.css', [], SPF_VERSION );
    wp_register_script( 'spf-script', SPF_URL . 'assets/js/script.js', [ 'jquery' ], SPF_VERSION, true );
    wp_localize_script( 'spf-script', 'spfData', [
        'version' => SPF_VERSION,
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'spf_nonce' ),
    ] );
}

/**
 * Keep filtered URLs functional on a normal page load too.
 * The AJAX endpoint uses the same filter contract independently.
 */
add_action( 'woocommerce_product_query', 'spf_apply_archive_filters', 99999 );
function spf_apply_archive_filters( $query ) {
    if ( is_admin() ) return;

    $cats = [];
    if ( ! empty( $_GET['spf_cats'] ) ) {
        $raw = sanitize_text_field( wp_unslash( $_GET['spf_cats'] ) );
        $cats = array_values( array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', $raw ) ) ) );
    }

    if ( $cats ) {
        $tax_query = (array) $query->get( 'tax_query' );
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $cats,
            'operator' => 'IN',
        ];
        $query->set( 'tax_query', $tax_query );
    }

    $min = isset( $_GET['spf_min_price'] ) ? max( 0, (float) $_GET['spf_min_price'] ) : 0;
    $max = isset( $_GET['spf_max_price'] ) ? max( 0, (float) $_GET['spf_max_price'] ) : 0;

    if ( $min || $max ) {
        $meta_query = (array) $query->get( 'meta_query' );

        if ( $min ) {
            $meta_query[] = [
                'key'     => '_price',
                'value'   => $min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $max ) {
            $meta_query[] = [
                'key'     => '_price',
                'value'   => $max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ];
        }

        $query->set( 'meta_query', $meta_query );
    }
}

