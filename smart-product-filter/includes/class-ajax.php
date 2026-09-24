<?php
namespace SPF\Ajax;

if ( ! defined( 'ABSPATH' ) ) exit;

class Handler {
    public function __construct() {
        add_action('wp_ajax_spf_filter', [$this, 'filter_products']);
        add_action('wp_ajax_nopriv_spf_filter', [$this, 'filter_products']);
    }

    private function clean_slugs($value) {
        if ( ! is_array($value) ) {
            $value = preg_split('/\s*,\s*/', (string)$value);
        }
        $out = [];
        foreach ($value as $slug) {
            $slug = sanitize_title(wp_unslash($slug));
            if ($slug !== '') $out[] = $slug;
        }
        return array_values(array_unique($out));
    }

    private function base_query($base_cat, $cats, $min, $max) {
        $tax_query = ['relation' => 'AND'];

        // When the user has selected categories, they replace the archive category.
        // Otherwise keep the current product-category archive scope.
        $scope = $cats ? $cats : ($base_cat ? [$base_cat] : []);
        if ($scope) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $scope,
                'operator' => 'IN',
            ];
        }

        $meta_query = ['relation' => 'AND'];
        if ($min > 0) {
            $meta_query[] = [
                'key' => '_price',
                'value' => $min,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ];
        }
        if ($max > 0) {
            $meta_query[] = [
                'key' => '_price',
                'value' => $max,
                'compare' => '<=',
                'type' => 'NUMERIC',
            ];
        }

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
            'no_found_rows' => false,
            'paged' => 1,
            'posts_per_page' => apply_filters(
                'loop_shop_per_page',
                wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()
            ),
            'tax_query' => count($tax_query) > 1 ? $tax_query : [],
            'meta_query' => count($meta_query) > 1 ? $meta_query : [],
        ];

        return $args;
    }

    private function ordering($args, $orderby, $order) {
        $orderby = sanitize_key($orderby);
        $order = strtoupper(sanitize_key($order)) === 'ASC' ? 'ASC' : 'DESC';

        if ( function_exists('WC') && WC()->query instanceof \WC_Query ) {
            $catalog = WC()->query->get_catalog_ordering_args($orderby, $order);
            if ( ! empty($catalog['orderby']) ) $args['orderby'] = $catalog['orderby'];
            if ( ! empty($catalog['order']) ) $args['order'] = $catalog['order'];
            if ( isset($catalog['meta_key']) ) $args['meta_key'] = $catalog['meta_key'];
        } else {
            $args['orderby'] = $orderby ?: 'menu_order';
            $args['order'] = $order;
        }
        return $args;
    }

    public function filter_products() {
        if ( ! check_ajax_referer('spf_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'درخواست نامعتبر است.'], 403);
        }

        if ( ! function_exists('WC') || ! class_exists('WooCommerce') ) {
            wp_send_json_error(['message' => 'WooCommerce فعال نیست.'], 500);
        }

        $cats = isset($_POST['cats']) ? $this->clean_slugs($_POST['cats']) : [];
        $base_cat = isset($_POST['base_cat']) ? sanitize_title(wp_unslash($_POST['base_cat'])) : '';
        $min = isset($_POST['min']) ? max(0, (float)$_POST['min']) : 0;
        $max = isset($_POST['max']) ? max(0, (float)$_POST['max']) : 0;
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
        $orderby = isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : '';
        $order = isset($_POST['order']) ? sanitize_key(wp_unslash($_POST['order'])) : 'DESC';

        $args = $this->base_query($base_cat, $cats, $min, $max);
        $args['paged'] = $paged;
        $args = $this->ordering($args, $orderby, $order);

        // Let WooCommerce and theme integrations modify the product query.
        $args = apply_filters('spf_filter_query_args', $args, $_POST);

        $query = new \WP_Query($args);

        ob_start();
        if ($query->have_posts()) {
            woocommerce_product_loop_start();
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
            woocommerce_product_loop_end();
        } else {
            echo '<p class="spf-no-results">' . esc_html__('محصولی با این فیلترها یافت نشد.', 'spf') . '</p>';
        }
        $products_html = ob_get_clean();

        $total = (int)$query->found_posts;
        $per_page = (int)$query->get('posts_per_page');
        $pages = $per_page > 0 ? (int)ceil($total / $per_page) : 1;

        ob_start();
        if ($pages > 1) {
            $base = add_query_arg('paged', '%#%', home_url('/'));
            echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__('Product Pagination', 'woocommerce') . '">';
            echo paginate_links([
                'base' => $base,
                'format' => '',
                'current' => $paged,
                'total' => $pages,
                'type' => 'list',
                'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
                'next_text' => is_rtl() ? '&larr;' : '&rarr;',
                'end_size' => 2,
                'mid_size' => 2,
            ]);
            echo '</nav>';
        }
        $pagination_html = ob_get_clean();

        $first = $total ? (($paged - 1) * $per_page + 1) : 0;
        $last = min($total, $paged * $per_page);
        $count_html = $total
            ? sprintf(
                esc_html__('نمایش %1$d–%2$d از %3$d محصول', 'spf'),
                $first, $last, $total
            )
            : '';

        wp_reset_postdata();

        wp_send_json_success([
            'html' => $products_html,
            'pagination' => $pagination_html,
            'count' => $count_html,
            'total' => $total,
            'pages' => $pages,
            'paged' => $paged,
        ]);
    }
}

new Handler();
