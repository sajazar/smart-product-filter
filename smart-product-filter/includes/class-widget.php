<?php
namespace SPF\Widget;

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) return;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Smart_Filter extends Widget_Base {

    public function get_name() { return 'spf_smart_filter'; }
    public function get_title() { return 'فیلتر هوشمند محصولات'; }
    public function get_icon() { return 'eicon-filter'; }
    public function get_categories() { return [ 'spf-widgets', 'woocommerce-elements' ]; }
    public function get_keywords() { return [ 'filter', 'فیلتر', 'woocommerce', 'product', 'woodmart' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'section_cats', [ 'label' => 'دسته‌بندی‌ها' ] );

        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        $options = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $options[ $term->slug ] = $term->name . ' (' . $term->count . ')';
            }
        }

        $this->add_control( 'selected_cats', [
            'label'       => 'دسته‌بندی‌های قابل نمایش',
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $options,
        ] );

        $this->add_control( 'show_cat_filter', [
            'label'   => 'نمایش فیلتر دسته‌بندی',
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'parent_cat', [
            'label'     => 'محدود کردن به این دسته',
            'type'      => Controls_Manager::SELECT,
            'options'   => [ '' => 'همه' ] + $options,
            'condition' => [ 'show_cat_filter' => 'yes' ],
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'section_price', [ 'label' => 'فیلتر قیمت' ] );

        $this->add_control( 'show_price_filter', [
            'label'   => 'نمایش فیلتر قیمت',
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'price_min', [
            'label'     => 'حداقل قیمت',
            'type'      => Controls_Manager::NUMBER,
            'default'   => 0,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->add_control( 'price_max', [
            'label'     => 'حداکثر قیمت',
            'type'      => Controls_Manager::NUMBER,
            'default'   => 10000000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->add_control( 'price_step', [
            'label'     => 'گام اسلایدر',
            'type'      => Controls_Manager::NUMBER,
            'default'   => 50000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->end_controls_section();
    }

    private function get_terms_to_show( $selected, $parent ) {
        if ( $parent ) {
            $root = get_term_by( 'slug', $parent, 'product_cat' );

            if ( $root && ! is_wp_error( $root ) ) {
                $children = get_terms([
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => true,
                    'parent'     => $root->term_id,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ]);

                if ( ! is_wp_error( $children ) && $children ) {
                    return $children;
                }

                return [ $root ];
            }
        }

        if ( $selected ) {
            $selected_terms = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'slug'       => (array) $selected,
                'orderby'    => 'include',
            ]);

            if ( ! is_wp_error( $selected_terms ) && $selected_terms ) {
                return $selected_terms;
            }
        }

        // Default: show every non-empty top-level category.
        // This is deliberately independent of WoodMart's widget/query state.
        $roots = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if ( ! is_wp_error( $roots ) && $roots ) {
            return $roots;
        }

        // Fallback: some stores have no non-empty root categories because
        // products live only in child categories. In that case show all
        // non-empty categories so the filter can never render empty.
        $all = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        return is_wp_error( $all ) ? [] : $all;
    }

    private function get_current_category_slug() {
        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $object = get_queried_object();

            if ( $object instanceof \WP_Term && 'product_cat' === $object->taxonomy ) {
                return $object->slug;
            }
        }

        return '';
    }

    private function render_term( $term, $current_slug, $level = 0 ) {
        if ( $level > 2 ) {
            return;
        }

        $children = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $term->term_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        $has_children = ! is_wp_error( $children ) && ! empty( $children );
        $is_current   = $current_slug === $term->slug;
        $term_url     = get_term_link( $term, 'product_cat' );

        if ( is_wp_error( $term_url ) ) {
            $term_url = home_url( '/' );
        }

        $classes = [
            'cat-item',
            'spf-cat-level-' . absint( $level ),
        ];

        if ( $has_children ) {
            $classes[] = 'has-children';
        }

        if ( $is_current ) {
            $classes[] = 'current-cat';
            $classes[] = 'wd-active';
        }
        ?>
        <li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
            <!-- IMPORTANT: WoodMart binds its filter click handler to li > .pf-value.
                 Keep the anchor as the direct child of li. -->
            <a
                class="pf-value spf-cat-link"
                href="<?php echo esc_url( $term_url ); ?>"
                data-val="<?php echo esc_attr( $term->slug ); ?>"
                data-title="<?php echo esc_attr( $term->name ); ?>"
            >
                <span class="spf-cat-name"><?php echo esc_html( $term->name ); ?></span>
                <span class="spf-cat-count">(<?php echo absint( $term->count ); ?>)</span>
            </a>

            <?php if ( $has_children && $level < 2 ) : ?>
                <ul class="spf-cat-children" <?php echo $is_current ? '' : 'style="display:none;"'; ?>>
                    <?php foreach ( $children as $child ) {
                        $this->render_term( $child, $current_slug, $level + 1 );
                    } ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }

    private function render_price_filter( $min, $max, $step ) {
        $current_min = isset( $_GET['min_price'] ) ? (float) wc_clean( wp_unslash( $_GET['min_price'] ) ) : $min;
        $current_max = isset( $_GET['max_price'] ) ? (float) wc_clean( wp_unslash( $_GET['max_price'] ) ) : $max;

        $current_min = max( $min, min( $current_min, $max ) );
        $current_max = max( $current_min, min( $current_max, $max ) );

        $display_min = number_format_i18n( $current_min );
        $display_max = number_format_i18n( $current_max );

        $price_url = function_exists( 'wc_get_page_permalink' )
            ? wc_get_page_permalink( 'shop' )
            : home_url( '/' );
        ?>
        <div class="spf-block spf-block-price">
            <div class="wd-pf-checkboxes wd-pf-price-range">
                <div class="wd-pf-title">
                    <span class="title-text">محدوده قیمت</span>
                    <ul class="wd-pf-results">
                        <?php if ( $current_min !== $min || $current_max !== $max ) : ?>
                            <li class="selected-value" data-title="price-filter">
                                <?php echo esc_html( $display_min . ' - ' . $display_max ); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="wd-pf-dropdown wd-dropdown">
                    <div class="wd-scroll">
                        <div class="wd-scroll-content">
                            <div class="price_slider_widget"></div>
                            <div class="filter_price_slider_amount">
                                <span class="from"><?php echo esc_html( $display_min ); ?></span>
                                <span class="to"><?php echo esc_html( $display_max ); ?></span>

                                <input type="hidden" class="min_price" name="min_price"
                                    value="<?php echo ( $current_min !== $min ) ? esc_attr( $current_min ) : ''; ?>"
                                    data-min="<?php echo esc_attr( $min ); ?>"
                                    data-max="<?php echo esc_attr( $max ); ?>">

                                <input type="hidden" class="max_price" name="max_price"
                                    value="<?php echo ( $current_max !== $max ) ? esc_attr( $current_max ) : ''; ?>"
                                    data-min="<?php echo esc_attr( $min ); ?>"
                                    data-max="<?php echo esc_attr( $max ); ?>">

                                <a class="pf-value" href="<?php echo esc_url( $price_url ); ?>"
                                    data-val="price" data-title="price-filter">اعمال محدوده قیمت</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        wp_enqueue_style( 'spf-style' );

        if ( wp_script_is( 'product-filters', 'registered' ) || wp_script_is( 'product-filters', 'enqueued' ) ) {
            wp_enqueue_script( 'product-filters' );
        }

        $selected = ! empty( $settings['selected_cats'] ) ? (array) $settings['selected_cats'] : [];
        $parent   = ! empty( $settings['parent_cat'] ) ? $settings['parent_cat'] : '';

        $terms        = $this->get_terms_to_show( $selected, $parent );
        $current_slug = $this->get_current_category_slug();
        $form_action  = function_exists( 'woodmart_filters_get_page_base_url' )
            ? woodmart_filters_get_page_base_url()
            : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) );

        $min  = max( 0, (float) $settings['price_min'] );
        $max  = max( $min, (float) $settings['price_max'] );
        $step = max( 1, (float) $settings['price_step'] );

        $id = 'spf-' . $this->get_id();
        ?>
        <div id="<?php echo esc_attr( $id ); ?>" class="spf-wrap wd-spf-custom">
            <form class="wd-product-filters with-ajax"
                action="<?php echo esc_url( $form_action ); ?>" method="GET">

                <?php if ( 'yes' === $settings['show_cat_filter'] ) : ?>
                    <div class="spf-block spf-block-cats">
                        <div class="wd-pf-checkboxes wd-pf-categories">
                            <div class="wd-pf-title" tabindex="0">
                                <span class="title-text">دسته‌بندی</span>
                                <ul class="wd-pf-results">
                                    <?php if ( $current_slug ) :
                                        $current_term = get_term_by( 'slug', $current_slug, 'product_cat' );
                                        if ( $current_term && ! is_wp_error( $current_term ) ) : ?>
                                            <li class="selected-value" data-title="<?php echo esc_attr( $current_slug ); ?>">
                                                <?php echo esc_html( $current_term->name ); ?>
                                            </li>
                                        <?php endif;
                                    endif; ?>
                                </ul>
                            </div>

                            <div class="wd-pf-dropdown wd-dropdown">
                                <div class="wd-scroll">
                                    <ul class="wd-scroll-content">
                                        <?php
                                        if ( $terms ) {
                                            foreach ( $terms as $term ) {
                                                $this->render_term( $term, $current_slug );
                                            }
                                        } else {
                                            echo '<li class="cat-item spf-empty">هیچ دسته‌بندی فعالی برای نمایش پیدا نشد.</li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( 'yes' === $settings['show_price_filter'] ) {
                    $this->render_price_filter( $min, $max, $step );
                } ?>
            </form>
        </div>
        <?php
    }
}
