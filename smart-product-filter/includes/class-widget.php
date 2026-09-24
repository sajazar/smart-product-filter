<?php
namespace SPF\Widget;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
    return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

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

        // ============ بخش دسته‌بندی ============
        $this->start_controls_section( 'section_cats', [
            'label' => 'دسته‌بندی‌ها',
        ]);

        $product_cats = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);

        $cat_options = [];
        if ( ! is_wp_error( $product_cats ) ) {
            foreach ( $product_cats as $cat ) {
                $cat_options[ $cat->slug ] = $cat->name . ' (' . $cat->count . ')';
            }
        }

        $this->add_control( 'selected_cats', [
            'label'       => 'دسته‌بندی‌های مورد نمایش',
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $cat_options,
        ]);

        $this->add_control( 'show_cat_filter', [
            'label'   => 'نمایش فیلتر دسته‌بندی',
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        // والد برای فیلتر
        $this->add_control( 'parent_cat', [
            'label'       => 'فیلتر بر اساس زیرشاخه‌های این دسته',
            'type'        => Controls_Manager::SELECT,
            'options'     => [ '' => 'همه' ] + $cat_options,
            'description' => 'اگر انتخاب کنی، فقط زیرشاخه‌های این دسته در فیلتر نمایش داده می‌شن.',
            'condition'   => [ 'show_cat_filter' => 'yes' ],
        ]);

        $this->end_controls_section();

        // ============ بخش قیمت ============
        $this->start_controls_section( 'section_price', [
            'label' => 'فیلتر قیمت',
        ]);

        $this->add_control( 'show_price_filter', [
            'label'   => 'نمایش فیلتر قیمت',
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control( 'price_min', [
            'label'   => 'حداقل قیمت',
            'type'    => Controls_Manager::NUMBER,
            'default' => 0,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ]);

        $this->add_control( 'price_max', [
            'label'   => 'حداکثر قیمت',
            'type'    => Controls_Manager::NUMBER,
            'default' => 10000000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ]);

        $this->add_control( 'price_step', [
            'label'   => 'گام اسلایدر',
            'type'    => Controls_Manager::NUMBER,
            'default' => 50000,
            'condition' => [ 'show_price_filter' => 'yes' ],
        ]);

        $this->end_controls_section();
    }

   protected function render() {
    $settings = $this->get_settings_for_display();

    wp_enqueue_style( 'spf-style' );
    wp_enqueue_script( 'spf-script' );

    $selected_cats = ! empty( $settings['selected_cats'] ) ? (array) $settings['selected_cats'] : [];

    $widget_id = 'spf-' . $this->get_id();

    // پارامترهای فعلی
    $current_cats = isset( $_GET['spf_cats'] ) ? explode( ',', sanitize_text_field( $_GET['spf_cats'] ) ) : [];
    $current_min  = isset( $_GET['spf_min_price'] ) ? floatval( $_GET['spf_min_price'] ) : $settings['price_min'];
    $current_max  = isset( $_GET['spf_max_price'] ) ? floatval( $_GET['spf_max_price'] ) : $settings['price_max'];

    ?>
    <div class="spf-wrap" 
         id="<?php echo esc_attr( $widget_id ); ?>"
         data-min="<?php echo esc_attr( $settings['price_min'] ); ?>"
         data-max="<?php echo esc_attr( $settings['price_max'] ); ?>"
         data-step="<?php echo esc_attr( $settings['price_step'] ); ?>">

        <div class="spf-inner">

            <?php if ( $settings['show_cat_filter'] === 'yes' ) : ?>
                <?php
                // اگر دسته‌های خاص انتخاب شدن، از اونها استفاده کن
                // در غیر این صورت همه دسته‌های سطح بالا
                if ( ! empty( $selected_cats ) ) {
                    $top_terms = get_terms([
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'slug'       => $selected_cats,
                        'orderby'    => 'include',
                    ]);
                } else {
                    $top_terms = get_terms([
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'parent'     => 0,
                    ]);
                }

                if ( ! is_wp_error( $top_terms ) && ! empty( $top_terms ) ) :
                ?>
                <div class="spf-block spf-block-cats">
                    <h4 class="spf-title">دسته‌بندی</h4>
                    <ul class="spf-cat-list">
                        <?php
                        foreach ( $top_terms as $term ) {
                            // چک می‌کنیم که زیرشاخه داشته باشه
                            $children = get_terms([
                                'taxonomy'   => 'product_cat',
                                'hide_empty' => true,
                                'parent'     => $term->term_id,
                            ]);
                            $has_children = ! is_wp_error( $children ) && ! empty( $children );
                            $is_checked   = in_array( $term->slug, $current_cats, true );

                            // چک کن که آیا زیرشاخه‌ای انتخاب شده
                            $has_checked_child = false;
                            if ( $has_children ) {
                                foreach ( $children as $child ) {
                                    if ( in_array( $child->slug, $current_cats, true ) ) {
                                        $has_checked_child = true;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <li class="spf-cat-parent <?php echo $has_children ? 'has-children' : ''; ?>">
                                <label class="spf-cat-item spf-cat-item-parent">
                                    <?php if ( $has_children ) : ?>
                                        <span class="spf-toggle <?php echo ( $is_checked || $has_checked_child ) ? 'open' : ''; ?>"></span>
                                    <?php else : ?>
                                        <span class="spf-toggle-placeholder"></span>
                                    <?php endif; ?>
                                    <input type="checkbox" 
                                           class="spf-cat-checkbox" 
                                           value="<?php echo esc_attr( $term->slug ); ?>"
                                           <?php checked( $is_checked ); ?>>
                                    <span class="spf-cat-name"><?php echo esc_html( $term->name ); ?></span>
                                    <span class="spf-cat-count">(<?php echo intval( $term->count ); ?>)</span>
                                </label>

                                <?php if ( $has_children ) : ?>
                                    <ul class="spf-cat-children" style="<?php echo ( $is_checked || $has_checked_child ) ? '' : 'display:none;'; ?>">
                                        <?php foreach ( $children as $child ) : 
                                            $child_checked = in_array( $child->slug, $current_cats, true );
                                            // زیرشاخه‌های سطح سوم
                                            $grandchildren = get_terms([
                                                'taxonomy'   => 'product_cat',
                                                'hide_empty' => true,
                                                'parent'     => $child->term_id,
                                            ]);
                                            $child_has_children = ! is_wp_error( $grandchildren ) && ! empty( $grandchildren );
                                            $child_has_checked_grandchild = false;
                                            if ( $child_has_children ) {
                                                foreach ( $grandchildren as $gc ) {
                                                    if ( in_array( $gc->slug, $current_cats, true ) ) {
                                                        $child_has_checked_grandchild = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>
                                            <li class="spf-cat-child <?php echo $child_has_children ? 'has-children' : ''; ?>">
                                                <label class="spf-cat-item">
                                                    <?php if ( $child_has_children ) : ?>
                                                        <span class="spf-toggle <?php echo ( $child_checked || $child_has_checked_grandchild ) ? 'open' : ''; ?>"></span>
                                                    <?php else : ?>
                                                        <span class="spf-toggle-placeholder"></span>
                                                    <?php endif; ?>
                                                    <input type="checkbox" 
                                                           class="spf-cat-checkbox" 
                                                           value="<?php echo esc_attr( $child->slug ); ?>"
                                                           <?php checked( $child_checked ); ?>>
                                                    <span class="spf-cat-name"><?php echo esc_html( $child->name ); ?></span>
                                                    <span class="spf-cat-count">(<?php echo intval( $child->count ); ?>)</span>
                                                </label>

                                                <?php if ( $child_has_children ) : ?>
                                                    <ul class="spf-cat-grandchildren" style="<?php echo ( $child_checked || $child_has_checked_grandchild ) ? '' : 'display:none;'; ?>">
                                                        <?php foreach ( $grandchildren as $gc ) : 
                                                            $gc_checked = in_array( $gc->slug, $current_cats, true );
                                                        ?>
                                                            <li class="spf-cat-grandchild">
                                                                <label class="spf-cat-item">
                                                                    <span class="spf-toggle-placeholder"></span>
                                                                    <input type="checkbox" 
                                                                           class="spf-cat-checkbox" 
                                                                           value="<?php echo esc_attr( $gc->slug ); ?>"
                                                                           <?php checked( $gc_checked ); ?>>
                                                                    <span class="spf-cat-name"><?php echo esc_html( $gc->name ); ?></span>
                                                                    <span class="spf-cat-count">(<?php echo intval( $gc->count ); ?>)</span>
                                                                </label>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $settings['show_price_filter'] === 'yes' ) : ?>
            <div class="spf-block spf-block-price">
                <h4 class="spf-title">محدوده قیمت</h4>
                <div class="spf-price-display">
                    <span class="spf-price-from"><?php echo number_format( $current_min ); ?></span>
                    <span class="spf-price-sep">تا</span>
                    <span class="spf-price-to"><?php echo number_format( $current_max ); ?></span>
                    <span class="spf-price-unit">تومان</span>
                </div>
                <div class="spf-slider-wrap">
                    <div class="spf-slider-track"></div>
                    <div class="spf-slider-range" style="left:0;right:0;"></div>
                    <input type="range" class="spf-range spf-range-min" 
                           min="<?php echo esc_attr( $settings['price_min'] ); ?>"
                           max="<?php echo esc_attr( $settings['price_max'] ); ?>"
                           step="<?php echo esc_attr( $settings['price_step'] ); ?>"
                           value="<?php echo esc_attr( $current_min ); ?>">
                    <input type="range" class="spf-range spf-range-max" 
                           min="<?php echo esc_attr( $settings['price_min'] ); ?>"
                           max="<?php echo esc_attr( $settings['price_max'] ); ?>"
                           step="<?php echo esc_attr( $settings['price_step'] ); ?>"
                           value="<?php echo esc_attr( $current_max ); ?>">
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
}
}