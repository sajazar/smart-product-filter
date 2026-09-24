<?php
/**
 * Plugin Name: Smart Product Filter for Elementor
 * Description: فیلتر هوشمند محصولات بر اساس دسته‌بندی خاص و قیمت
 * Version: 1.0.2
 * Author: Custom
 * Text Domain: spf
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SPF_VERSION', '1.0.2' );
define( 'SPF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPF_URL', plugin_dir_url( __FILE__ ) );

/**
 * بارگذاری کلاس AJAX (نیازی به المنتور نداره، پس آزاده)
 */
require_once SPF_PATH . 'includes/class-ajax.php';

/**
 * ثبت ویجت المنتور
 * این هوک فقط بعد از لود کامل المنتور اجرا می‌شه، پس کلاس Widget_Base وجود داره
 */
add_action( 'elementor/widgets/register', 'spf_register_widget' );
function spf_register_widget( $widgets_manager ) {

    // چک ایمنی: اگر کلاس پایه المنتور نبود، خارج شو
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    require_once SPF_PATH . 'includes/class-widget.php';

    if ( class_exists( '\SPF\Widget\Smart_Filter' ) ) {
        $widgets_manager->register( new \SPF\Widget\Smart_Filter() );
    }
}

/**
 * ثبت دسته‌بندی ویجت‌ها در المنتور
 */
add_action( 'elementor/elements/categories_registered', 'spf_register_category' );
function spf_register_category( $elements_manager ) {
    $elements_manager->add_category( 'spf-widgets', [
        'title' => 'فیلتر هوشمند',
        'icon'  => 'fa fa-filter',
    ]);
}

/**
 * بارگذاری استایل و اسکریپت در فرانت
 */
add_action( 'wp_enqueue_scripts', 'spf_enqueue_assets' );
function spf_enqueue_assets() {
    wp_register_style( 'spf-style', SPF_URL . 'assets/css/style.css', [], SPF_VERSION );
    wp_register_script( 'spf-script', SPF_URL . 'assets/js/script.js', [ 'jquery' ], SPF_VERSION, true );
    wp_localize_script( 'spf-script', 'spfData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'spf_nonce' ),
    ]);
}

/**
 * اعمال فیلتر روی Query ووکامرس بر اساس URL
 */
add_action( 'woocommerce_product_query', 'spf_apply_query_filters' );
function spf_apply_query_filters( $q ) {
    if ( is_admin() ) return;

    // فیلتر دسته
    if ( ! empty( $_GET['spf_cats'] ) ) {
        $cats = array_map( 'sanitize_title', explode( ',', sanitize_text_field( $_GET['spf_cats'] ) ) );

        $tax_query = (array) $q->get( 'tax_query' );

        // حذف tax_query های قبلی مربوط به product_cat
        // تا تداخل نکنن
        $tax_query = array_filter( $tax_query, function( $tq ) {
            return ! ( isset( $tq['taxonomy'] ) && $tq['taxonomy'] === 'product_cat' );
        });

        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $cats,
            'operator' => 'IN',
        ];
        $q->set( 'tax_query', $tax_query );

        // مهم: پرچم می‌ذاریم که این یه فیلتر دسته‌ست
        $q->set( 'spf_is_filtered', true );
    }

    // فیلتر قیمت
    if ( ! empty( $_GET['spf_min_price'] ) || ! empty( $_GET['spf_max_price'] ) ) {
        $meta_query = (array) $q->get( 'meta_query' );
        $min = floatval( $_GET['spf_min_price'] );
        $max = floatval( $_GET['spf_max_price'] );

        $price_query = [ 'relation' => 'AND' ];
        if ( $min ) {
            $price_query[] = [
                'key'     => '_price',
                'value'   => $min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }
        if ( $max ) {
            $price_query[] = [
                'key'     => '_price',
                'value'   => $max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ];
        }
        $meta_query[] = $price_query;
        $q->set( 'meta_query', $meta_query );
    }
}