<?php
namespace SPF\Ajax;
if ( ! defined( 'ABSPATH' ) ) exit;
class Handler {
    public function __construct() { add_action( 'wp_ajax_spf_filter', [ $this, 'filter_products' ] ); add_action( 'wp_ajax_nopriv_spf_filter', [ $this, 'filter_products' ] ); }
    private function slugs( $value ) { $value = is_array( $value ) ? $value : preg_split( '/\s*,\s*/', (string) $value ); $out = []; foreach ( $value as $slug ) { $slug = sanitize_title( wp_unslash( $slug ) ); if ( $slug ) $out[] = $slug; } return array_values( array_unique( $out ) ); }
    public function filter_products() {
        if ( ! check_ajax_referer( 'spf_nonce', 'nonce', false ) ) wp_send_json_error( [ 'message' => 'درخواست نامعتبر است.' ], 403 );
        if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) wp_send_json_error( [ 'message' => 'WooCommerce فعال نیست.' ], 500 );
        $cats = isset( $_POST['cats'] ) ? $this->slugs( $_POST['cats'] ) : [];
        $base = isset( $_POST['base_cat'] ) ? sanitize_title( wp_unslash( $_POST['base_cat'] ) ) : '';
        $min = isset( $_POST['min'] ) ? max( 0, (float) $_POST['min'] ) : 0;
        $max = isset( $_POST['max'] ) ? max( 0, (float) $_POST['max'] ) : 0;
        $tax = [];
        $scope = $cats ?: ( $base ? [ $base ] : [] );
        if ( $scope ) $tax[] = [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $scope, 'operator' => 'IN' ];
        $meta = [];
        if ( $min > 0 ) $meta[] = [ 'key' => '_price', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC' ];
        if ( $max > 0 ) $meta[] = [ 'key' => '_price', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC' ];
        $args = [ 'post_type' => 'product', 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'no_found_rows' => false, 'paged' => max( 1, absint( $_POST['paged'] ?? 1 ) ), 'posts_per_page' => apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ), 'tax_query' => $tax, 'meta_query' => $meta ];
        $orderby = sanitize_key( wp_unslash( $_POST['orderby'] ?? '' ) );
        $order = strtoupper( sanitize_key( wp_unslash( $_POST['order'] ?? 'DESC' ) ) ) === 'ASC' ? 'ASC' : 'DESC';
        if ( WC()->query instanceof \WC_Query ) $args = array_merge( $args, WC()->query->get_catalog_ordering_args( $orderby, $order ) );
        $args = apply_filters( 'spf_filter_query_args', $args, $_POST );
        $query = new \WP_Query( $args ); ob_start();
        if ( $query->have_posts() ) { woocommerce_product_loop_start(); while ( $query->have_posts() ) { $query->the_post(); wc_get_template_part( 'content', 'product' ); } woocommerce_product_loop_end(); } else echo '<p class="spf-no-results">' . esc_html__( 'محصولی با این فیلترها یافت نشد.', 'spf' ) . '</p>';
        $html = ob_get_clean(); $total = (int) $query->found_posts; $per_page = max( 1, (int) $query->get( 'posts_per_page' ) ); $pages = max( 1, (int) ceil( $total / $per_page ) ); ob_start();
        if ( $pages > 1 ) echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__( 'Product Pagination', 'woocommerce' ) . '">' . paginate_links( [ 'base' => add_query_arg( 'paged', '%#%', home_url( '/' ) ), 'format' => '', 'current' => (int) $args['paged'], 'total' => $pages, 'type' => 'list' ] ) . '</nav>';
        $pagination = ob_get_clean(); wp_reset_postdata(); wp_send_json_success( [ 'html' => $html, 'pagination' => $pagination, 'total' => $total, 'pages' => $pages ] );
    }
}
new Handler();
