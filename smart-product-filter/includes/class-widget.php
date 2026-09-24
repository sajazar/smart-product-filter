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
    public function get_keywords() { return [ 'filter', 'فیلتر', 'woocommerce', 'product' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'section_cats', [ 'label' => 'دسته‌بندی‌ها' ] );

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $options = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $options[$term->slug] = $term->name . ' (' . $term->count . ')';
            }
        }

        $this->add_control( 'selected_cats', [
            'label' => 'دسته‌بندی‌های قابل نمایش',
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $options,
        ] );

        $this->add_control( 'show_cat_filter', [
            'label' => 'نمایش فیلتر دسته‌بندی',
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'parent_cat', [
            'label' => 'محدود کردن به این دسته',
            'type' => Controls_Manager::SELECT,
            'options' => [ '' => 'همه' ] + $options,
            'condition' => [ 'show_cat_filter' => 'yes' ],
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'section_price', [ 'label' => 'فیلتر قیمت' ] );

        $this->add_control( 'show_price_filter', [
            'label' => 'نمایش فیلتر قیمت',
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'price_min', [
            'label' => 'حداقل قیمت',
            'type' => Controls_Manager::NUMBER,
            'default' => 0,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->add_control( 'price_max', [
            'label' => 'حداکثر قیمت',
            'type' => Controls_Manager::NUMBER,
            'default' => 10000000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->add_control( 'price_step', [
            'label' => 'گام اسلایدر',
            'type' => Controls_Manager::NUMBER,
            'default' => 50000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ] );

        $this->end_controls_section();
    }

    private function get_terms_to_show( $selected, $parent ) {
        if ( $parent ) {
            $root = get_term_by( 'slug', $parent, 'product_cat' );
            if ( $root && ! is_wp_error( $root ) ) {
                $children = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => true,
                    'parent' => $root->term_id,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ]);
                if ( ! is_wp_error( $children ) && $children ) {
                    return $children;
                }
                return [ $root ];
            }
        }

        if ( $selected ) {
            $selected_terms = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'slug' => (array) $selected,
                'orderby' => 'include',
            ]);
            return is_wp_error( $selected_terms ) ? [] : $selected_terms;
        }

        $roots = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        return is_wp_error( $roots ) ? [] : $roots;
    }

    private function current_cats() {
        if ( empty( $_GET['spf_cats'] ) ) return [];
        $raw = sanitize_text_field( wp_unslash( $_GET['spf_cats'] ) );
        return array_values(array_filter(array_map('sanitize_title', preg_split('/\s*,\s*/', $raw))));
    }

    private function render_term( $term, $current, $level = 0 ) {
        if ( $level > 2 ) return;

        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => $term->term_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $has_children = ! is_wp_error($children) && ! empty($children);
        $checked = in_array($term->slug, $current, true);
        ?>
        <li class="spf-cat-level-<?php echo esc_attr($level); ?><?php echo $has_children ? ' has-children' : ''; ?>">
            <label class="spf-cat-item">
                <?php if ( $has_children ) : ?>
                    <span class="spf-toggle <?php echo $checked ? 'open' : ''; ?>"></span>
                <?php else : ?>
                    <span class="spf-toggle-placeholder"></span>
                <?php endif; ?>
                <input type="checkbox" class="spf-cat-checkbox" value="<?php echo esc_attr($term->slug); ?>" <?php checked($checked); ?>>
                <span class="spf-cat-name"><?php echo esc_html($term->name); ?></span>
                <span class="spf-cat-count">(<?php echo absint($term->count); ?>)</span>
            </label>
            <?php if ( $has_children && $level < 2 ) : ?>
                <ul class="<?php echo $level === 0 ? 'spf-cat-children' : 'spf-cat-grandchildren'; ?>" style="<?php echo $checked ? '' : 'display:none;'; ?>">
                    <?php foreach ( $children as $child ) $this->render_term($child, $current, $level + 1); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        wp_enqueue_style('spf-style');
        wp_enqueue_script('spf-script');

        $selected = ! empty($settings['selected_cats']) ? (array)$settings['selected_cats'] : [];
        $parent = ! empty($settings['parent_cat']) ? $settings['parent_cat'] : '';
        $terms = $this->get_terms_to_show($selected, $parent);
        $current = $this->current_cats();

        $min = max(0, (float)$settings['price_min']);
        $max = max($min, (float)$settings['price_max']);
        $cur_min = isset($_GET['spf_min_price']) ? (float)$_GET['spf_min_price'] : $min;
        $cur_max = isset($_GET['spf_max_price']) ? (float)$_GET['spf_max_price'] : $max;
        $cur_min = max($min, min($cur_min, $max));
        $cur_max = max($cur_min, min($cur_max, $max));
        $step = max(1, (float)$settings['price_step']);

        $base_cat = '';
        if ( function_exists('is_product_category') && is_product_category() ) {
            $obj = get_queried_object();
            if ( $obj instanceof \WP_Term && $obj->taxonomy === 'product_cat' ) $base_cat = $obj->slug;
        }

        $id = 'spf-' . $this->get_id();
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="spf-wrap"
             data-min="<?php echo esc_attr($min); ?>"
             data-max="<?php echo esc_attr($max); ?>"
             data-step="<?php echo esc_attr($step); ?>"
             data-base-cat="<?php echo esc_attr($base_cat); ?>">
            <?php if ( $settings['show_cat_filter'] === 'yes' && $terms ) : ?>
                <div class="spf-block spf-block-cats">
                    <h4 class="spf-title">دسته‌بندی</h4>
                    <ul class="spf-cat-list">
                        <?php foreach ($terms as $term) $this->render_term($term, $current); ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ( $settings['show_price_filter'] === 'yes' ) : ?>
                <div class="spf-block spf-block-price">
                    <h4 class="spf-title">محدوده قیمت</h4>
                    <div class="spf-price-display">
                        <span class="spf-price-from"><?php echo esc_html(number_format($cur_min)); ?></span>
                        <span class="spf-price-sep">تا</span>
                        <span class="spf-price-to"><?php echo esc_html(number_format($cur_max)); ?></span>
                        <span class="spf-price-unit">تومان</span>
                    </div>
                    <div class="spf-slider-wrap">
                        <div class="spf-slider-track"></div>
                        <div class="spf-slider-range"></div>
                        <input type="range" class="spf-range spf-range-min" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($cur_min); ?>">
                        <input type="range" class="spf-range spf-range-max" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($cur_max); ?>">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
