<?php
namespace SPF\Widget;

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

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
        $terms = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ]);
        $options = [];
        if ( ! is_wp_error( $terms ) ) foreach ( $terms as $term ) $options[ $term->slug ] = $term->name . ' (' . $term->count . ')';
        $this->add_control( 'selected_cats', [ 'label' => 'دسته‌بندی‌های قابل نمایش', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $options ] );
        $this->add_control( 'show_cat_filter', [ 'label' => 'نمایش فیلتر دسته‌بندی', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ] );
        $this->add_control( 'parent_cat', [ 'label' => 'محدود کردن به این دسته', 'type' => Controls_Manager::SELECT, 'options' => [ '' => 'همه' ] + $options, 'condition' => [ 'show_cat_filter' => 'yes' ] ] );
        $this->end_controls_section();
        $this->start_controls_section( 'section_price', [ 'label' => 'فیلتر قیمت' ] );
        $this->add_control( 'show_price_filter', [ 'label' => 'نمایش فیلتر قیمت', 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ] );
        $this->add_control( 'price_min', [ 'label' => 'حداقل قیمت', 'type' => Controls_Manager::NUMBER, 'default' => 0, 'condition' => [ 'show_price_filter' => 'yes' ] ] );
        $this->add_control( 'price_max', [ 'label' => 'حداکثر قیمت', 'type' => Controls_Manager::NUMBER, 'default' => 10000000, 'condition' => [ 'show_price_filter' => 'yes' ] ] );
        $this->add_control( 'price_step', [ 'label' => 'گام اسلایدر', 'type' => Controls_Manager::NUMBER, 'default' => 50000, 'condition' => [ 'show_price_filter' => 'yes' ] ] );
        $this->end_controls_section();
    }

    private function get_terms_to_show( $selected, $parent ) {
        if ( $parent ) {
            $root = get_term_by( 'slug', $parent, 'product_cat' );
            if ( $root && ! is_wp_error( $root ) ) {
                $children = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $root->term_id, 'orderby' => 'name', 'order' => 'ASC' ]);
                return ( ! is_wp_error( $children ) && $children ) ? $children : [ $root ];
            }
        }
        if ( $selected ) {
            $terms = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'slug' => (array) $selected, 'orderby' => 'include' ]);
            if ( ! is_wp_error( $terms ) && $terms ) return $terms;
        }
        $roots = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'orderby' => 'name', 'order' => 'ASC' ]);
        if ( ! is_wp_error( $roots ) && $roots ) return $roots;
        $all = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ]);
        return is_wp_error( $all ) ? [] : $all;
    }

    private function current_category() {
        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $object = get_queried_object();
            if ( $object instanceof \WP_Term && 'product_cat' === $object->taxonomy ) return $object->slug;
        }
        return '';
    }

    private function render_term( $term, $current_slug, $level = 0 ) {
        if ( $level > 2 ) return;
        $children = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $term->term_id, 'orderby' => 'name', 'order' => 'ASC' ]);
        $has_children = ! is_wp_error( $children ) && ! empty( $children );
        $url = get_term_link( $term, 'product_cat' );
        if ( is_wp_error( $url ) ) $url = home_url( '/' );
        $classes = [ 'cat-item', 'spf-cat-level-' . absint( $level ) ];
        if ( $has_children ) $classes[] = 'has-children';
        if ( $current_slug === $term->slug ) $classes[] = 'current-cat wd-active';
        ?><li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"><a class="pf-value spf-cat-link" href="<?php echo esc_url( $url ); ?>" data-val="<?php echo esc_attr( $term->slug ); ?>" data-title="<?php echo esc_attr( $term->name ); ?>"><span class="spf-cat-name"><?php echo esc_html( $term->name ); ?></span><span class="spf-cat-count">(<?php echo absint( $term->count ); ?>)</span></a><?php if ( $has_children && $level < 2 ) : ?><ul class="spf-cat-children" style="display:block;"><?php foreach ( $children as $child ) $this->render_term( $child, $current_slug, $level + 1 ); ?></ul><?php endif; ?></li><?php
a    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        wp_enqueue_style( 'spf-style' );
        wp_enqueue_script( 'spf-script' );
        $selected = ! empty( $settings['selected_cats'] ) ? (array) $settings['selected_cats'] : [];
        $parent = ! empty( $settings['parent_cat'] ) ? $settings['parent_cat'] : '';
        $terms = $this->get_terms_to_show( $selected, $parent );
        $current = $this->current_category();
        $min = isset( $settings['price_min'] ) && $settings['price_min'] !== '' ? max( 0, (float) $settings['price_min'] ) : 0;
        $max = isset( $settings['price_max'] ) && $settings['price_max'] !== '' ? max( $min, (float) $settings['price_max'] ) : 10000000;
        $step = isset( $settings['price_step'] ) && $settings['price_step'] !== '' ? max( 1, (float) $settings['price_step'] ) : 50000;
        ?><div id="spf-<?php echo esc_attr( $this->get_id() ); ?>" class="spf-wrap wd-spf-custom" data-base-cat="<?php echo esc_attr( $current ); ?>" data-min="<?php echo esc_attr( $min ); ?>" data-max="<?php echo esc_attr( $max ); ?>" data-step="<?php echo esc_attr( $step ); ?>"><form class="spf-form" action="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>" method="get"><?php if ( 'yes' === $settings['show_cat_filter'] ) : ?><div class="spf-block spf-block-cats"><div class="wd-pf-checkboxes wd-pf-categories"><div class="wd-pf-title" tabindex="0"><span class="title-text">دسته‌بندی</span><ul class="wd-pf-results"></ul></div><div class="wd-pf-dropdown wd-dropdown"><div class="wd-scroll"><ul class="wd-scroll-content"><?php if ( $terms ) foreach ( $terms as $term ) $this->render_term( $term, $current ); else echo '<li class="cat-item spf-empty">هیچ دسته‌بندی فعالی برای نمایش پیدا نشد.</li>'; ?></ul></div></div></div></div><?php endif; ?><?php if ( 'yes' === $settings['show_price_filter'] ) : ?><div class="spf-block spf-block-price"><label>محدوده قیمت</label><div class="spf-price-controls"><input class="spf-min-price" type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $min ); ?>"><span> تا </span><input class="spf-max-price" type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $max ); ?>"><button type="submit" class="spf-apply">اعمال</button></div></div><?php endif; ?></form><div class="spf-status" aria-live="polite"></div></div><?php
    }
}
