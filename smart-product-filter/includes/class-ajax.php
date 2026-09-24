<?php
namespace SPF\Ajax;

if ( ! defined( 'ABSPATH' ) ) exit;

class Handler {

    public function __construct() {
        add_action( 'wp_ajax_spf_filter', [ $this, 'filter_products' ] );
        add_action( 'wp_ajax_nopriv_spf_filter', [ $this, 'filter_products' ] );
    }

    public function filter_products() {
        check_ajax_referer( 'spf_nonce', 'nonce' );

        $cats = isset( $_POST['cats'] ) ? array_map( 'sanitize_title', (array) $_POST['cats'] ) : [];
        $min  = isset( $_POST['min'] ) ? floatval( $_POST['min'] ) : 0;
        $max  = isset( $_POST['max'] ) ? floatval( $_POST['max'] ) : 0;
        $paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => apply_filters( 'spf_products_per_page', 12 ),
            'paged'          => $paged,
            'tax_query'      => [],
            'meta_query'     => [],
        ];

        // فیلتر دسته
        if ( ! empty( $cats ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $cats,
                'operator' => 'IN',
            ];
        }

        // فیلتر قیمت
        if ( $min || $max ) {
            $price_q = [ 'relation' => 'AND' ];
            if ( $min ) {
                $price_q[] = [
                    'key'     => '_price',
                    'value'   => $min,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }
            if ( $max ) {
                $price_q[] = [
                    'key'     => '_price',
                    'value'   => $max,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ];
            }
            $args['meta_query'][] = $price_q;
        }

        $query = new \WP_Query( $args );

        ob_start();

        if ( $query->have_posts() ) {
            woocommerce_product_loop_start();
            while ( $query->have_posts() ) {
                $query->the_post();
                wc_get_template_part( 'content', 'product' );
            }
            woocommerce_product_loop_end();
        } else {
            echo '<p class="spf-no-results">محصولی با این فیلترها یافت نشد.</p>';
        }

        $html = ob_get_clean();
        wp_reset_postdata();

        wp_send_json_success([
            'html'  => $html,
            'count' => $query->found_posts,
        ]);
    }
}

new Handler();