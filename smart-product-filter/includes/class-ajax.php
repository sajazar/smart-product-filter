<?php
namespace SPF\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Handler {

    public function __construct() {
        add_action( 'wp_ajax_spf_filter', [ $this, 'filter_products' ] );
        add_action( 'wp_ajax_nopriv_spf_filter', [ $this, 'filter_products' ] );
    }

    public function filter_products() {

        check_ajax_referer( 'spf_nonce', 'nonce' );

        $cats = isset( $_POST['cats'] )
            ? array_filter( array_map( 'sanitize_title', (array) wp_unslash( $_POST['cats'] ) ) )
            : [];

        $min = isset( $_POST['min'] )
            ? max( 0, floatval( wp_unslash( $_POST['min'] ) ) )
            : 0;

        $max = isset( $_POST['max'] )
            ? max( 0, floatval( wp_unslash( $_POST['max'] ) ) )
            : 0;

        $paged = isset( $_POST['paged'] )
            ? max( 1, absint( $_POST['paged'] ) )
            : 1;

        $args = [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'posts_per_page'      => apply_filters( 'spf_products_per_page', 12 ),
            'paged'               => $paged,
            'ignore_sticky_posts' => true,
            'tax_query'           => [],
            'meta_query'          => [],
        ];

        if ( ! empty( $cats ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $cats,
                'operator' => 'IN',
            ];
        }

        if ( $min > 0 || $max > 0 ) {
            $price_query = [
                'relation' => 'AND',
            ];

            if ( $min > 0 ) {
                $price_query[] = [
                    'key'     => '_price',
                    'value'   => $min,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }

            if ( $max > 0 ) {
                $price_query[] = [
                    'key'     => '_price',
                    'value'   => $max,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ];
            }

            $args['meta_query'][] = $price_query;
        }

        if ( function_exists( 'wc_get_catalog_ordering_args' ) ) {
            $ordering = wc_get_catalog_ordering_args();

            if ( ! empty( $ordering['orderby'] ) ) {
                $args['orderby'] = $ordering['orderby'];
            }

            if ( ! empty( $ordering['order'] ) ) {
                $args['order'] = $ordering['order'];
            }

            if ( ! empty( $ordering['meta_key'] ) ) {
                $args['meta_key'] = $ordering['meta_key'];
            }
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

        wp_send_json_success(
            [
                'html'  => $html,
                'count' => (int) $query->found_posts,
            ]
        );
    }
}

new Handler();
