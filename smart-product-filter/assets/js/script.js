(function($) {
    'use strict';

    var request = null;

    function selectors() {
        return (window.spfData && spfData.productsSelectors) || [
            '.wd-products ul.products',
            '.wd-products-element ul.products',
            '.shop-content ul.products',
            '.site-content ul.products',
            'ul.products'
        ];
    }

    function getProducts($root) {
        var $scope = $root && $root.length ? $root : $(document);
        var $found = $();
        $.each(selectors(), function(_, selector) {
            var $el = $scope.find(selector).first();
            if (!$el.length && $scope.is(selector)) $el = $scope.first();
            if ($el.length) {
                $found = $el;
                return false;
            }
        });
        return $found;
    }

    function formatPrice(n) {
        return Number(n).toLocaleString('fa-IR');
    }

    function parseCats($wrap) {
        return $wrap.find('.spf-cat-checkbox:checked').map(function() {
            return this.value;
        }).get();
    }

    function buildUrl($wrap) {
        var url = new URL(window.location.href);
        var min = parseFloat($wrap.find('.spf-range-min').val());
        var max = parseFloat($wrap.find('.spf-range-max').val());
        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;
        var cats = parseCats($wrap);

        url.searchParams.delete('paged');
        url.searchParams.delete('product_cat');
        url.searchParams.delete('spf_cats');
        url.searchParams.delete('spf_min_price');
        url.searchParams.delete('spf_max_price');

        if (cats.length) {
            url.searchParams.set('filter_category', cats.join(','));
        } else {
            url.searchParams.delete('filter_category');
        }

        if (!isNaN(min) && min > absMin) {
            url.searchParams.set('min_price', Math.round(min));
        } else {
            url.searchParams.delete('min_price');
        }

        if (!isNaN(max) && max < absMax) {
            url.searchParams.set('max_price', Math.round(max));
        } else {
            url.searchParams.delete('max_price');
        }

        return url.toString();
    }

    function replacePart($current, $new) {
        if ($current.length && $new.length) {
            $current.replaceWith($new);
            return true;
        }
        return false;
    }

    function replaceResponse(html) {
        var $page = $('<div>').append($.parseHTML(html, document, true));
        var $current = getProducts();
        var $new = getProducts($page);

        if (!$current.length || !$new.length) return false;

        replacePart($current, $new);

        var pairs = [
            ['.woocommerce-result-count', '.woocommerce-result-count'],
            ['.woocommerce-ordering', '.woocommerce-ordering'],
            ['.wd-shop-tools .woocommerce-result-count', '.wd-shop-tools .woocommerce-result-count'],
            ['.wd-shop-tools .woocommerce-ordering', '.wd-shop-tools .woocommerce-ordering'],
            ['.woocommerce-pagination', '.woocommerce-pagination']
        ];

        $.each(pairs, function(_, pair) {
            var $old = $(pair[0]).first();
            var $fresh = $page.find(pair[1]).first();

            if ($old.length && $fresh.length) {
                $old.replaceWith($fresh);
            } else if ($old.length && !$fresh.length && pair[0] === '.woocommerce-pagination') {
                $old.remove();
            } else if (!$old.length && $fresh.length && pair[0] === '.woocommerce-pagination') {
                $('.products').last().after($fresh);
            }
        });

        var title = $page.find('title').text();
        if (title) document.title = title;

        var $newH1 = $page.find('h1').first();
        var $oldH1 = $('h1').first();
        if ($newH1.length && $oldH1.length) $oldH1.html($newH1.html());

        $(document.body).trigger('updated_wc_div');
        $(document).trigger('wdShopPageInit');
        $(window).trigger('resize');

        return true;
    }

    function loadUrl(url, $wrap, pushState) {
        if (request && request.readyState !== 4) request.abort();

        var $products = getProducts();
        if (!$products.length) {
            window.location.href = url;
            return;
        }

        $wrap.addClass('spf-loading');
        $products.addClass('spf-products-loading');

        request = $.ajax({
            method: 'GET',
            url: url,
            dataType: 'html',
            cache: true,
            headers: {
                'X-Braapf': '1',
                'X-SPF': '1'
            }
        }).done(function(html) {
            if (!replaceResponse(html)) {
                window.location.href = url;
                return;
            }

            if (pushState) {
                window.history.pushState({ spf: true }, '', url);
            }
        }).fail(function(xhr, status) {
            if (status !== 'abort') window.location.href = url;
        }).always(function() {
            $wrap.removeClass('spf-loading');
            $('.spf-products-loading').removeClass('spf-products-loading');
        });
    }

    function initFilter($wrap) {
        if ($wrap.data('spf-init')) return;
        $wrap.data('spf-init', true);

        var $min = $wrap.find('.spf-range-min');
        var $max = $wrap.find('.spf-range-max');
        var $range = $wrap.find('.spf-slider-range');
        var $from = $wrap.find('.spf-price-from');
        var $to = $wrap.find('.spf-price-to');

        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;
        var step = parseFloat($wrap.data('step')) || 1;

        function updateSlider() {
            if (!$min.length || !$max.length) return;

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

        updateSlider();

        var timer = null;
        $min.add($max).on('input', function() {
            updateSlider();
            clearTimeout(timer);
            timer = setTimeout(function() {
                loadUrl(buildUrl($wrap), $wrap, true);
            }, 500);
        });

        $wrap.on('change', '.spf-cat-checkbox', function() {
            loadUrl(buildUrl($wrap), $wrap, true);
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

    window.addEventListener('popstate', function(event) {
        var $wrap = $('.spf-wrap').first();
        if (!$wrap.length) return;

        if (request && request.readyState !== 4) request.abort();

        var $products = getProducts();
        if (!$products.length) {
            window.location.reload();
            return;
        }

        $wrap.addClass('spf-loading');
        $products.addClass('spf-products-loading');

        request = $.ajax({
            method: 'GET',
            url: window.location.href,
            dataType: 'html',
            cache: true,
            headers: {
                'X-Braapf': '1',
                'X-SPF': '1'
            }
        }).done(function(html) {
            if (!replaceResponse(html)) window.location.reload();
        }).fail(function(xhr, status) {
            if (status !== 'abort') window.location.reload();
        }).always(function() {
            $wrap.removeClass('spf-loading');
            $('.spf-products-loading').removeClass('spf-products-loading');
        });
    });

})(jQuery);
