<?php
namespace SPF\Widget;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
    return;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Smart_Filter extends Widget_Base {

    public function get_name() {
        return 'spf_smart_filter';
    }

    public function get_title() {
        return 'فیلتر هوشمند محصولات';
    }

    public function get_icon() {
        return 'eicon-filter';
    }

    public function get_categories() {
        return [ 'spf-widgets', 'woocommerce-elements' ];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'section_cats',
            [
                'label' => 'دسته‌بندی‌ها',
            ]
        );

        $product_cats = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $cat_options = [];

        if ( ! is_wp_error( $product_cats ) ) {
            foreach ( $product_cats as $cat ) {
                $cat_options[ $cat->slug ] = $cat->name . ' (' . $cat->count . ')';
            }
        }

        $this->add_control(
            'selected_cats',
            [
                'label'       => 'دسته‌بندی‌های قابل نمایش',
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'label_block' => true,
                'options'     => $cat_options,
            ]
        );

        $this->add_control(
            'show_cat_filter',
            [
                'label'   => 'نمایش فیلتر دسته‌بندی',
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'parent_cat',
            [
                'label'       => 'محدود کردن به این دسته و زیرشاخه‌ها',
                'type'        => Controls_Manager::SELECT,
                'options'     => [ '' => 'همه' ] + $cat_options,
                'description' => 'اگر انتخاب شود، این دسته و زیرشاخه‌هایش نمایش داده می‌شوند.',
                'condition'   => [ 'show_cat_filter' => 'yes' ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_price',
            [
                'label' => 'فیلتر قیمت',
            ]
        );

        $this->add_control(
            'show_price_filter',
            [
                'label'   => 'نمایش فیلتر قیمت',
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'price_min',
            [
                'label'     => 'حداقل قیمت',
                'type'      => Controls_Manager::NUMBER,
                'default'   => 0,
                'condition' => [ 'show_price_filter' => 'yes' ],
            ]
        );

        $this->add_control(
            'price_max',
            [
                'label'     => 'حداکثر قیمت',
                'type'      => Controls_Manager::NUMBER,
                'default'   => 10000000,
                'condition' => [ 'show_price_filter' => 'yes' ],
            ]
        );

        $this->add_control(
            'price_step',
            [
                'label'     => 'گام اسلایدر',
                'type'      => Controls_Manager::NUMBER,
                'default'   => 50000,
                'condition' => [ 'show_price_filter' => 'yes' ],
            ]
        );

        $this->end_controls_section();
    }

    private function get_display_terms( $selected_cats, $parent_cat ) {

        if ( ! empty( $parent_cat ) ) {
            $root = get_term_by( 'slug', $parent_cat, 'product_cat' );

            if ( $root && ! is_wp_error( $root ) ) {
                return [ $root ];
            }
        }

        if ( ! empty( $selected_cats ) ) {
            $terms = get_terms(
                [
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => true,
                    'slug'       => $selected_cats,
                    'orderby'    => 'include',
                ]
            );

            return is_wp_error( $terms ) ? [] : $terms;
        }

        return get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );
    }

    private function get_current_category_slugs() {
        $slugs = [];

        if ( ! empty( $_GET['filter_category'] ) ) {
            $raw = sanitize_text_field( wp_unslash( $_GET['filter_category'] ) );
            $slugs = array_filter( array_map( 'sanitize_title', preg_split( '/\\s*,\\s*/', $raw ) ) );
        }

        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $term = get_queried_object();
            if ( $term instanceof \WP_Term && 'product_cat' === $term->taxonomy ) {
                $slugs[] = $term->slug;
            }
        }

        return array_values( array_unique( $slugs ) );
    }

    private function render_term( $term, $current_cats, $level = 0 ) {

        if ( $level > 2 ) {
            return;
        }

        $children = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => $term->term_id,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $has_children = ! is_wp_error( $children ) && ! empty( $children );
        $checked = in_array( $term->slug, $current_cats, true );
        $has_checked_child = false;

        if ( $has_children ) {
            foreach ( $children as $child ) {
                if ( in_array( $child->slug, $current_cats, true ) ) {
                    $has_checked_child = true;
                    break;
                }

                if ( $level < 2 ) {
                    $grandchildren = get_terms(
                        [
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => true,
                            'parent'     => $child->term_id,
                        ]
                    );

                    if ( ! is_wp_error( $grandchildren ) ) {
                        foreach ( $grandchildren as $gc ) {
                            if ( in_array( $gc->slug, $current_cats, true ) ) {
                                $has_checked_child = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $classes = [
            0 => 'spf-cat-parent',
            1 => 'spf-cat-child',
            2 => 'spf-cat-grandchild',
        ];

        $class = isset( $classes[ $level ] ) ? $classes[ $level ] : 'spf-cat-grandchild';
        ?>
        <li class="<?php echo esc_attr( $class . ( $has_children ? ' has-children' : '' ) ); ?>">
            <label class="spf-cat-item">
                <?php if ( $has_children ) : ?>
                    <span class="spf-toggle <?php echo ( $checked || $has_checked_child ) ? 'open' : ''; ?>"></span>
                <?php else : ?>
                    <span class="spf-toggle-placeholder"></span>
                <?php endif; ?>

                <input
                    type="checkbox"
                    class="spf-cat-checkbox"
                    value="<?php echo esc_attr( $term->slug ); ?>"
                    <?php checked( $checked ); ?>
                >

                <span class="spf-cat-name"><?php echo esc_html( $term->name ); ?></span>
                <span class="spf-cat-count">(<?php echo intval( $term->count ); ?>)</span>
            </label>

            <?php if ( $has_children && $level < 2 ) : ?>
                <ul
                    class="<?php echo 0 === $level ? 'spf-cat-children' : 'spf-cat-grandchildren'; ?>"
                    style="<?php echo ( $checked || $has_checked_child ) ? '' : 'display:none;'; ?>"
                >
                    <?php
                    foreach ( $children as $child ) {
                        $this->render_term( $child, $current_cats, $level + 1 );
                    }
                    ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }

    protected function render() {

        $settings = $this->get_settings_for_display();

        wp_enqueue_style( 'spf-style' );
        wp_enqueue_script( 'spf-script' );

        $selected_cats = ! empty( $settings['selected_cats'] )
            ? (array) $settings['selected_cats']
            : [];

        $current_cats = $this->get_current_category_slugs();

        $default_min = max( 0, floatval( $settings['price_min'] ) );
        $default_max = max( $default_min, floatval( $settings['price_max'] ) );

        $current_min = isset( $_GET['min_price'] )
            ? floatval( $_GET['min_price'] )
            : $default_min;

        $current_max = isset( $_GET['max_price'] )
            ? floatval( $_GET['max_price'] )
            : $default_max;

        $current_min = max( $default_min, $current_min );
        $current_max = min( $default_max, $current_max );

        if ( $current_min > $current_max ) {
            $current_min = $default_min;
            $current_max = $default_max;
        }

        $display_terms = $this->get_display_terms(
            $selected_cats,
            ! empty( $settings['parent_cat'] ) ? $settings['parent_cat'] : ''
        );

        $widget_id = 'spf-' . $this->get_id();
        ?>
        <div
            class="spf-wrap"
            id="<?php echo esc_attr( $widget_id ); ?>"
            data-min="<?php echo esc_attr( $default_min ); ?>"
            data-max="<?php echo esc_attr( $default_max ); ?>"
            data-step="<?php echo esc_attr( max( 1, floatval( $settings['price_step'] ) ) ); ?>"
        >
            <div class="spf-inner">

                <?php if ( 'yes' === $settings['show_cat_filter'] && ! empty( $display_terms ) && ! is_wp_error( $display_terms ) ) : ?>
                    <div class="spf-block spf-block-cats">
                        <h4 class="spf-title">دسته‌بندی</h4>
                        <ul class="spf-cat-list">
                            <?php
                            foreach ( $display_terms as $term ) {
                                $this->render_term( $term, $current_cats );
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ( 'yes' === $settings['show_price_filter'] ) : ?>
                    <div class="spf-block spf-block-price">
                        <h4 class="spf-title">محدوده قیمت</h4>

                        <div class="spf-price-display">
                            <span class="spf-price-from"><?php echo esc_html( number_format( $current_min ) ); ?></span>
                            <span class="spf-price-sep">تا</span>
                            <span class="spf-price-to"><?php echo esc_html( number_format( $current_max ) ); ?></span>
                            <span class="spf-price-unit">تومان</span>
                        </div>

                        <div class="spf-slider-wrap">
                            <div class="spf-slider-track"></div>
                            <div class="spf-slider-range" style="left:0;right:0;"></div>

                            <input
                                type="range"
                                class="spf-range spf-range-min"
                                min="<?php echo esc_attr( $default_min ); ?>"
                                max="<?php echo esc_attr( $default_max ); ?>"
                                step="<?php echo esc_attr( max( 1, floatval( $settings['price_step'] ) ) ); ?>"
                                value="<?php echo esc_attr( $current_min ); ?>"
                            >

                            <input
                                type="range"
                                class="spf-range spf-range-max"
                                min="<?php echo esc_attr( $default_min ); ?>"
                                max="<?php echo esc_attr( $default_max ); ?>"
                                step="<?php echo esc_attr( max( 1, floatval( $settings['price_step'] ) ) ); ?>"
                                value="<?php echo esc_attr( $current_max ); ?>"
                            >
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
