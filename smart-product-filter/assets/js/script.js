(function($) {
    'use strict';
    var request = null;

    function initFilter($wrap) {
        if (!$wrap.length || $wrap.data('spf-ready')) return;
        $wrap.data('spf-ready', true);

        // WoodMart normally opens this dropdown through its own filter script.
        // Keep the widget usable even when that optional script is not loaded.
        $wrap.on('click.spf', '.spf-block-cats .wd-pf-title', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $dropdown = $(this).closest('.wd-pf-checkboxes').find('.wd-pf-dropdown').first();
            $dropdown.stop(true, true).slideToggle(180);
            $(this).toggleClass('spf-open');
        });

        $wrap.on('click.spf', '.spf-cat-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $link = $(this), $form = $wrap.find('.spf-form');
            $wrap.find('.spf-cat-link').removeClass('wd-active');
            $link.addClass('wd-active');
            $wrap.find('.wd-pf-results').html('<li class="selected-value">' + $('<div>').text($link.data('title') || '').html() + '</li>');
            load($wrap, [$link.data('val')], $form.find('.spf-min-price').val(), $form.find('.spf-max-price').val(), 1);
        });

        $wrap.on('click.spf', '.spf-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('li').children('.spf-cat-children').stop(true, true).slideToggle(180);
            $(this).toggleClass('open');
        });

        $wrap.on('submit.spf', '.spf-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            load($wrap, [], $form.find('.spf-min-price').val(), $form.find('.spf-max-price').val(), 1);
        });
    }

    function productsTarget() {
        return $('.shop-content ul.products, .woocommerce ul.products, ul.products').filter(':visible').first();
    }

    function load($wrap, cats, min, max, paged) {
        var $target = productsTarget();
        if (!$target.length) {
            $wrap.find('.spf-status').text('لیست محصولات در این صفحه پیدا نشد.');
            return;
        }
        if (request && request.readyState !== 4) request.abort();
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
                $wrap.find('.spf-status').text(response && response.data ? response.data.message : window.SPF_DATA.i18n.error);
                return;
            }
            var $new = $(response.data.html).filter('ul.products');
            if ($new.length) $target.replaceWith($new);
            else $target.replaceWith(response.data.html);
            $('.woocommerce-pagination').remove();
            if (response.data.pagination) $('.products').last().after(response.data.pagination);
            if (window.woodmartThemeModule && typeof window.woodmartThemeModule.wooInit === 'function') {
                window.woodmartThemeModule.wooInit();
            }
            $(document).trigger('wdShopPageInit');
        });
        request.always(function() {
            $wrap.removeClass('spf-loading');
            $('.products').removeClass('spf-products-loading');
        });
    }

    function initAll() {
        $('.spf-wrap').each(function() { initFilter($(this)); });
    }

    $(document).ready(initAll);
    $(document).on('wdShopPageInit pjax:success', initAll);
    $(window).on('elementor/frontend/init', function() {
        if (window.elementorFrontend && elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/spf_smart_filter.default', function($scope) {
                initFilter($scope.find('.spf-wrap'));
            });
        }
    });
})(jQuery);
