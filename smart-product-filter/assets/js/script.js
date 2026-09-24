(function($) {
    'use strict';

    var request = null;

    function productsSelector() {
        return [
            '.wd-products ul.products',
            '.wd-products-element ul.products',
            '.shop-content ul.products',
            '.site-content ul.products',
            'ul.products'
        ];
    }

    function getProducts() {
        var found = $();
        $.each(productsSelector(), function(_, selector) {
            var el = $(selector).first();
            if (el.length) {
                found = el;
                return false;
            }
        });
        return found;
    }

    function formatPrice(value) {
        return Number(value).toLocaleString('fa-IR');
    }

    function getCats($wrap) {
        return $wrap.find('.spf-cat-checkbox:checked').map(function() {
            return this.value;
        }).get();
    }

    function getStateUrl($wrap, paged) {
        var url = new URL(window.location.href);
        var cats = getCats($wrap);
        var min = parseFloat($wrap.find('.spf-range-min').val());
        var max = parseFloat($wrap.find('.spf-range-max').val());
        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;

        url.searchParams.delete('spf_cats');
        url.searchParams.delete('spf_min_price');
        url.searchParams.delete('spf_max_price');
        url.searchParams.delete('paged');

        if (cats.length) {
            url.searchParams.set('spf_cats', cats.join(','));
        }

        if (!isNaN(min) && min > absMin) {
            url.searchParams.set('spf_min_price', Math.round(min));
        }

        if (!isNaN(max) && max < absMax) {
            url.searchParams.set('spf_max_price', Math.round(max));
        }

        if (paged && paged > 1) {
            url.searchParams.set('paged', paged);
        }

        return url;
    }

    function syncFromUrl($wrap) {
        var url = new URL(window.location.href);
        var cats = (url.searchParams.get('spf_cats') || '').split(',').filter(Boolean);
        var min = parseFloat(url.searchParams.get('spf_min_price'));
        var max = parseFloat(url.searchParams.get('spf_max_price'));

        $wrap.find('.spf-cat-checkbox').each(function() {
            $(this).prop('checked', cats.indexOf(this.value) !== -1);
        });

        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;

        if (!isNaN(min)) $wrap.find('.spf-range-min').val(Math.max(absMin, Math.min(min, absMax)));
        if (!isNaN(max)) $wrap.find('.spf-range-max').val(Math.max(absMin, Math.min(max, absMax)));

        updateSlider($wrap);
    }

    function updateSlider($wrap) {
        var $min = $wrap.find('.spf-range-min');
        var $max = $wrap.find('.spf-range-max');
        var $range = $wrap.find('.spf-slider-range');
        var $from = $wrap.find('.spf-price-from');
        var $to = $wrap.find('.spf-price-to');

        if (!$min.length || !$max.length) return;

        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;
        var step = parseFloat($wrap.data('step')) || 1;

        var min = parseFloat($min.val()) || absMin;
        var max = parseFloat($max.val()) || absMax;

        if (min > max - step) min = max - step;
        if (max < min + step) max = min + step;

        min = Math.max(absMin, min);
        max = Math.min(absMax, max);

        $min.val(min);
        $max.val(max);

        var total = absMax - absMin || 1;
        $range.css({
            left: (((min - absMin) / total) * 100) + '%',
            right: (100 - ((max - absMin) / total) * 100) + '%'
        });

        $from.text(formatPrice(min));
        $to.text(formatPrice(max));
    }

    function replaceProducts(html) {
        var $current = getProducts();
        var $newPage = $('<div>').append($.parseHTML(html, document, true));
        var $new = $newPage.find('ul.products').first();

        if (!$current.length || !$new.length) return false;

        $current.replaceWith($new);
        return true;
    }

    function replaceSelector(selector, html) {
        var $old = $(selector).first();
        if (!$old.length) return;
        if (html) {
            var $new = $('<div>').append($.parseHTML(html, document, true)).find(selector).first();
            if ($new.length) $old.replaceWith($new);
        } else {
            $old.remove();
        }
    }

    function load($wrap, paged, pushState) {
        if (request && request.readyState !== 4) request.abort();

        var url = getStateUrl($wrap, paged);
        var cats = getCats($wrap);
        var baseCat = $wrap.data('base-cat') || '';
        var min = parseFloat($wrap.find('.spf-range-min').val()) || 0;
        var max = parseFloat($wrap.find('.spf-range-max').val()) || 0;
        var orderby = new URL(window.location.href).searchParams.get('orderby') || '';
        var order = new URL(window.location.href).searchParams.get('order') || 'DESC';

        var $products = getProducts();
        if (!$products.length) {
            $wrap.removeClass('spf-loading');
            return;
        }

        $wrap.addClass('spf-loading');
        $products.addClass('spf-products-loading');

        request = $.ajax({
            url: spfData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'spf_filter',
                nonce: spfData.nonce,
                cats: cats,
                base_cat: baseCat,
                min: min,
                max: max,
                paged: paged || 1,
                orderby: orderby,
                order: order
            }
        }).done(function(res) {
            if (!res || !res.success) {
                console.error('SPF AJAX error:', res && res.data ? res.data : res);
                return;
            }

            if (!replaceProducts(res.data.html)) {
                console.error('SPF: product grid was not found in AJAX response.');
                return;
            }

            var $count = $('.woocommerce-result-count').first();
            if ($count.length) {
                $count.text(res.data.count || '');
            }

            var $pagination = $('.woocommerce-pagination').first();
            if ($pagination.length) {
                if (res.data.pagination) {
                    var $newPagination = $('<div>').append($.parseHTML(res.data.pagination, document, true)).find('.woocommerce-pagination').first();
                    if ($newPagination.length) $pagination.replaceWith($newPagination);
                } else {
                    $pagination.remove();
                }
            } else if (res.data.pagination) {
                $('.products').last().after(res.data.pagination);
            }

            if (pushState) {
                window.history.pushState({ spf: true }, '', url.toString());
            }

            $(document.body).trigger('updated_wc_div');
            $(document).trigger('wdShopPageInit');
            $(window).trigger('resize');
        }).fail(function(xhr, status) {
            if (status !== 'abort') {
                console.error('SPF AJAX request failed:', xhr.status, xhr.responseText);
            }
        }).always(function() {
            $wrap.removeClass('spf-loading');
            $('.spf-products-loading').removeClass('spf-products-loading');
        });
    }

    function initFilter($wrap) {
        if (!$wrap.length || $wrap.data('spf-init')) return;
        $wrap.data('spf-init', true);

        updateSlider($wrap);

        var timer = null;

        $wrap.on('input', '.spf-range-min, .spf-range-max', function() {
            updateSlider($wrap);
            clearTimeout(timer);
            timer = setTimeout(function() {
                load($wrap, 1, true);
            }, 500);
        });

        $wrap.on('change', '.spf-cat-checkbox', function() {
            load($wrap, 1, true);
        });

        $wrap.on('click', '.spf-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $toggle = $(this);
            var $children = $toggle.closest('li').children('.spf-cat-children, .spf-cat-grandchildren');
            if ($children.length) {
                $children.stop(true, true).slideToggle(180);
                $toggle.toggleClass('open');
            }
        });

        $(document).off('click.spfPagination').on('click.spfPagination', '.woocommerce-pagination a', function(e) {
            var $wrap = $('.spf-wrap').first();
            if (!$wrap.length) return;

            var href = $(this).attr('href') || '';
            if (!href) return;

            e.preventDefault();

            var pageUrl = new URL(href, window.location.origin);
            var paged = parseInt(pageUrl.searchParams.get('paged') || pageUrl.searchParams.get('product-page') || '1', 10);

            if (!paged && pageUrl.pathname.match(/\/page\/(\d+)/)) {
                paged = parseInt(RegExp.$1, 10);
            }

            load($wrap, paged || 1, true);
        });
    }

    function initAll() {
        $('.spf-wrap').each(function() {
            initFilter($(this));
        });
    }

    $(document).ready(initAll);

    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction(
                'frontend/element_ready/spf_smart_filter.default',
                function($scope) {
                    initFilter($scope.find('.spf-wrap'));
                }
            );
        }
    });

    window.addEventListener('popstate', function() {
        var $wrap = $('.spf-wrap').first();
        if (!$wrap.length) return;

        syncFromUrl($wrap);
        var paged = parseInt(new URL(window.location.href).searchParams.get('paged') || '1', 10);
        load($wrap, paged || 1, false);
    });

})(jQuery);
