(function($) {
    'use strict';

    // Fallback data if localization failed for any reason
    if (typeof window.SPF_DATA === 'undefined') {
        window.SPF_DATA = {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            nonce: '',
            i18n: { error: 'بارگذاری محصولات انجام نشد.' }
        };
    }

    var request = null;

    function productsTarget() {
        return $('.shop-content ul.products, .woocommerce ul.products, ul.products').filter(':visible').first();
    }

    function load($wrap, cats, min, max, paged, $link) {
        var $target = productsTarget();
        if (!$target.length) {
            $wrap.find('.spf-status').text('لیست محصولات در این صفحه پیدا نشد.');
            if ($link && $link.length) {
                window.location = $link.attr('href');
            }
            return;
        }

        if (request && request.readyState !== 4) {
            request.abort();
        }

        $target.addClass('spf-products-loading');
        $wrap.addClass('spf-loading');

        request = $.ajax({
            url: window.SPF_DATA.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'spf_filter',
                nonce: window.SPF_DATA.nonce,
                cats: cats,
                base_cat: $wrap.data('base-cat') || '',
                min: min || 0,
                max: max || 0,
                paged: paged || 1
            }
        });

        request.done(function(response) {
            if (!response || !response.success) {
                // Fallback to full navigation so user can still reach the category
                if ($link && $link.length) {
                    window.location = $link.attr('href');
                } else {
                    $wrap.find('.spf-status').text((response && response.data && response.data.message) || window.SPF_DATA.i18n.error);
                }
                return;
            }

            // Replace products list
            var $new = $('<div>').html(response.data.html).find('ul.products').first();
            if ($new.length) {
                $target.replaceWith($new);
            } else {
                // If server returned full html without ul.products wrapper
                $target.replaceWith(response.data.html);
            }

            // Replace pagination
            $('.woocommerce-pagination').remove();
            if (response.data.pagination) {
                $('.products').last().after(response.data.pagination);
            }

            // Theme compatibility hooks (WoodMart)
            try {
                if (window.woodmartThemeModule && typeof window.woodmartThemeModule.wooInit === 'function') {
                    window.woodmartThemeModule.wooInit();
                }
            } catch (e) {
                // ignore
            }

            $(document).trigger('wdShopPageInit');
        }).fail(function() {
            if ($link && $link.length) {
                window.location = $link.attr('href');
            }
        }).always(function() {
            $wrap.removeClass('spf-loading');
            $('.products').removeClass('spf-products-loading');
        });
    }

    function initFilter($wrap) {
        if (!$wrap.length || $wrap.data('spf-ui-init')) {
            return;
        }

        $wrap.data('spf-ui-init', true);

        // Toggle children (collapse/expand)
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

        // Category click — use AJAX, fallback to navigation
        $wrap.on('click.spfUI', '.spf-cat-link', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $link = $(this);
            var cats = [$link.data('val')];
            var $form = $wrap.find('.spf-form');
            var min = $form.find('.spf-min-price').val() || 0;
            var max = $form.find('.spf-max-price').val() || 0;

            // Update selected title area (if exists)
            $wrap.find('.wd-pf-results').html('<li class="selected-value" data-title="' + ($link.data('title') || '') + '">' + ($link.data('title') || '') + '</li>');

            load($wrap, cats, min, max, 1, $link);
        });

        // Form submit (price apply)
        $wrap.on('submit.spfUI', '.spf-form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $form = $(this);
            var min = $form.find('.spf-min-price').val() || 0;
            var max = $form.find('.spf-max-price').val() || 0;
            load($wrap, [], min, max, 1);
        });
    }

    function initAll() {
        $('.spf-wrap').each(function() {
            initFilter($(this));
        });
    }

    $(document).ready(initAll);

    $(document).on('wdShopPageInit pjax:success', function() {
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
