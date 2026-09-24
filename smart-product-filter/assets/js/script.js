/**
 * Smart Product Filter
 *
 * Filtering is handled by WoodMart's native productFilters.js + PJAX.
 * This file intentionally contains only the small UI behavior that is
 * specific to the Elementor widget.
 */
(function($) {
    'use strict';

    function initFilter($wrap) {
        if (!$wrap.length || $wrap.data('spf-ui-init')) {
            return;
        }

        $wrap.data('spf-ui-init', true);

        $wrap.on('click.spfUI', '.spf-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $toggle   = $(this);
            var $children = $toggle.closest('li').children('.spf-cat-children, .spf-cat-grandchildren');

            if (!$children.length) {
                return;
            }

            $children.stop(true, true).slideToggle(180);
            $toggle.toggleClass('open');
        });
    }

    function initAll() {
        $('.spf-wrap').each(function() {
            initFilter($(this));
        });
    }

    $(document).ready(initAll);

    $(document).on('wdShopPageInit', function() {
        initAll();
    });

    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend === 'undefined' || !elementorFrontend.hooks) {
            return;
        }

        elementorFrontend.hooks.addAction(
            'frontend/element_ready/spf_smart_filter.default',
            function($scope) {
                initFilter($scope.find('.spf-wrap'));
            }
        );
    });

})(jQuery);
