<?php
/**
 * Plugin Name: Smart Product Filter for Elementor
 * Description: فیلتر هوشمند محصولات بر اساس دسته‌بندی و قیمت برای Elementor + WooCommerce + WoodMart
 * Version: 1.3.0
 * Author: Custom
 * Text Domain: spf
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SPF_VERSION', '1.3.0' );
define( 'SPF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPF_URL', plugin_dir_url( __FILE__ ) );

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
        'shopUrl' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
        'productsSelectors' => [
            '.wd-products ul.products',
            '.wd-products-element ul.products',
            '.shop-content ul.products',
            '.site-content ul.products',
            'ul.products',
        ],
    ] );
}

/**
 * Match WoodMart's category-filter query variable and apply it to the
 * normal WooCommerce main query. No separate admin-ajax WP_Query is used.
 */
add_filter( 'woocommerce_product_query_tax_query', 'spf_apply_category_filter', 99999, 2 );
function spf_apply_category_filter( $tax_query, $query ) {
    if ( empty( $_GET['filter_category'] ) ) return $tax_query;

    $raw = sanitize_text_field( wp_unslash( $_GET['filter_category'] ) );
    $slugs = array_values( array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', $raw ) ) ) );

    if ( empty( $slugs ) ) return $tax_query;

    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $slugs,
        'operator' => 'IN',
    ];

    return $tax_query;
}

add_action( 'pre_get_posts', 'spf_apply_category_filter_fallback', 999999 );
function spf_apply_category_filter_fallback( $query ) {
    if ( is_admin() || ! $query->is_main_query() || empty( $_GET['filter_category'] ) ) return;

    $is_product_archive = function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() );
    if ( ! $is_product_archive ) return;

    $raw = sanitize_text_field( wp_unslash( $_GET['filter_category'] ) );
    $slugs = array_values( array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', $raw ) ) ) );
    if ( empty( $slugs ) ) return;

    $tax_query = (array) $query->get( 'tax_query' );
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $slugs,
        'operator' => 'IN',
    ];
    $query->set( 'tax_query', $tax_query );
}
