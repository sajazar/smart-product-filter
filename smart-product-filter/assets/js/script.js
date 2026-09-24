(function($) {
    'use strict';

    var request = null;

    function formatPrice(n) {
        return Number(n).toLocaleString('fa-IR');
    }

    function initFilter($wrap) {
        var $minRange = $wrap.find('.spf-range-min');
        var $maxRange = $wrap.find('.spf-range-max');
        var $range    = $wrap.find('.spf-slider-range');
        var $from     = $wrap.find('.spf-price-from');
        var $to       = $wrap.find('.spf-price-to');

        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;
        var step   = parseFloat($wrap.data('step')) || 1;

        function updateSlider() {
            if (!$minRange.length || !$maxRange.length) {
                return;
            }

            var minVal = parseFloat($minRange.val()) || absMin;
            var maxVal = parseFloat($maxRange.val()) || absMax;

            if (minVal > maxVal - step) {
                minVal = maxVal - step;
                $minRange.val(minVal);
            }

            if (maxVal < minVal + step) {
                maxVal = minVal + step;
                $maxRange.val(maxVal);
            }

            var range = absMax - absMin || 1;
            var leftPct = ((minVal - absMin) / range) * 100;
            var rightPct = 100 - ((maxVal - absMin) / range) * 100;

            $range.css({
                left: leftPct + '%',
                right: rightPct + '%'
            });

            $from.text(formatPrice(minVal));
            $to.text(formatPrice(maxVal));
        }

        function getSelectedCategories() {
            return $wrap.find('.spf-cat-checkbox:checked').map(function() {
                return this.value;
            }).get();
        }

        function getPriceValues() {
            return {
                min: $minRange.length ? (parseFloat($minRange.val()) || absMin) : absMin,
                max: $maxRange.length ? (parseFloat($maxRange.val()) || absMax) : absMax
            };
        }

        function getTermUrlBySlug(slug) {
            return $wrap.find('.spf-cat-checkbox').filter(function() {
                return this.value === slug;
            }).first().attr('data-term-url') || '';
        }

        function buildUrl() {
            var cats = getSelectedCategories();
            var price = getPriceValues();
            var url;

            if (cats.length === 1) {
                var termUrl = getTermUrlBySlug(cats[0]);
                url = new URL(termUrl || spfData.shopUrl, window.location.origin);
            } else {
                url = new URL(spfData.shopUrl, window.location.origin);

                if (cats.length > 1) {
                    url.searchParams.set('product_cat', cats.join(','));
                }
            }

            if (price.min > absMin) {
                url.searchParams.set('min_price', Math.round(price.min));
            } else {
                url.searchParams.delete('min_price');
            }

            if (price.max < absMax) {
                url.searchParams.set('max_price', Math.round(price.max));
            } else {
                url.searchParams.delete('max_price');
            }

            url.searchParams.delete('paged');
            url.searchParams.delete('spf_cats');
            url.searchParams.delete('spf_min_price');
            url.searchParams.delete('spf_max_price');

            return url.toString();
        }

        function getProductsSelector() {
            var selectors = [
                '.wd-products ul.products',
                '.wd-products-element ul.products',
                '.shop-content ul.products',
                '.site-content ul.products',
                'ul.products'
            ];

            for (var i = 0; i < selectors.length; i++) {
                var $el = $(selectors[i]).first();

                if ($el.length) {
                    return $el;
                }
            }

            return $();
        }

        function replaceFromResponse(html) {
            var $page = $('<div>').append($.parseHTML(html, document, true));
            var $currentProducts = getProductsSelector();

            if (!$currentProducts.length) {
                return false;
            }

            var $newProducts = $page.find('ul.products').first();

            if (!$newProducts.length) {
                return false;
            }

            $currentProducts.replaceWith($newProducts);

            var replacements = [
                ['.woocommerce-result-count', '.woocommerce-result-count'],
                ['.woocommerce-ordering', '.woocommerce-ordering'],
                ['.wd-shop-tools .woocommerce-result-count', '.wd-shop-tools .woocommerce-result-count'],
                ['.wd-shop-tools .woocommerce-ordering', '.wd-shop-tools .woocommerce-ordering']
            ];

            replacements.forEach(function(pair) {
                var $old = $(pair[0]).first();
                var $new = $page.find(pair[1]).first();

                if ($old.length && $new.length) {
                    $old.replaceWith($new);
                }
            });

            var $oldPagination = $('.woocommerce-pagination').first();
            var $newPagination = $page.find('.woocommerce-pagination').first();

            if ($oldPagination.length && $newPagination.length) {
                $oldPagination.replaceWith($newPagination);
            } else if ($newPagination.length && !$oldPagination.length) {
                $('.products').last().after($newPagination);
            } else if ($oldPagination.length && !$newPagination.length) {
                $oldPagination.remove();
            }

            var newTitle = $page.find('title').text();
            if (newTitle) {
                document.title = newTitle;
            }

            var $newH1 = $page.find('h1').first();
            var $oldH1 = $('h1').first();

            if ($newH1.length && $oldH1.length) {
                $oldH1.html($newH1.html());
            }

            $(document.body).trigger('updated_wc_div');
            $(document.body).trigger('wc_fragments_refreshed');
            $(document).trigger('wdShopPageInit');
            $(window).trigger('resize');

            return true;
        }

        function loadUrl(url, pushState) {
            if (request && request.readyState !== 4) {
                request.abort();
            }

            var $products = getProductsSelector();

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
                    'X-SPF': '1'
                }
            }).done(function(html) {
                if (replaceFromResponse(html)) {
                    if (pushState) {
                        window.history.pushState(
                            { spf: true },
                            '',
                            url
                        );
                    }
                } else {
                    window.location.href = url;
                }
            }).fail(function(xhr, status) {
                if (status !== 'abort') {
                    window.location.href = url;
                }
            }).always(function() {
                $wrap.removeClass('spf-loading');
                $('.spf-products-loading').removeClass('spf-products-loading');
            });
        }

        $minRange.on('input', function() {
            if (parseFloat($minRange.val()) > parseFloat($maxRange.val()) - step) {
                $minRange.val(parseFloat($maxRange.val()) - step);
            }

            updateSlider();
        });

        $maxRange.on('input', function() {
            if (parseFloat($maxRange.val()) < parseFloat($minRange.val()) + step) {
                $maxRange.val(parseFloat($minRange.val()) + step);
            }

            updateSlider();
        });

        updateSlider();

        var priceTimer;

        $minRange.add($maxRange).on('input', function() {
            clearTimeout(priceTimer);

            priceTimer = setTimeout(function() {
                loadUrl(buildUrl(), true);
            }, 600);
        });

        $wrap.on('change', '.spf-cat-checkbox', function() {
            loadUrl(buildUrl(), true);
        });

        $wrap.on('click', '.spf-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $toggle = $(this);
            var $li = $toggle.closest('li');
            var $children = $li.children('.spf-cat-children, .spf-cat-grandchildren');

            if ($children.length) {
                $children.stop(true, true).slideToggle(200);
                $toggle.toggleClass('open');
            }
        });
    }

    function initAll() {
        $('.spf-wrap').each(function() {
            if (!$(this).data('spf-init')) {
                $(this).data('spf-init', true);
                initFilter($(this));
            }
        });
    }

    $(document).ready(initAll);

    window.addEventListener('popstate', function() {
        var $wrap = $('.spf-wrap').first();

        if ($wrap.length) {
            loadFilterUrl(window.location.href, $wrap);
        }
    });

    function loadFilterUrl(url, $wrap) {
        if (request && request.readyState !== 4) {
            request.abort();
        }

        var $products = getGlobalProductsSelector();

        if (!$products.length) {
            window.location.reload();
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
                'X-SPF': '1'
            }
        }).done(function(html) {
            if (!replaceGlobalFromResponse(html)) {
                window.location.reload();
            }
        }).fail(function(xhr, status) {
            if (status !== 'abort') {
                window.location.reload();
            }
        }).always(function() {
            $wrap.removeClass('spf-loading');
            $('.spf-products-loading').removeClass('spf-products-loading');
        });
    }

    function getGlobalProductsSelector() {
        var selectors = [
            '.wd-products ul.products',
            '.wd-products-element ul.products',
            '.shop-content ul.products',
            '.site-content ul.products',
            'ul.products'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var $el = $(selectors[i]).first();

            if ($el.length) {
                return $el;
            }
        }

        return $();
    }

    function replaceGlobalFromResponse(html) {
        var $page = $('<div>').append($.parseHTML(html, document, true));
        var $currentProducts = getGlobalProductsSelector();
        var $newProducts = $page.find('ul.products').first();

        if (!$currentProducts.length || !$newProducts.length) {
            return false;
        }

        $currentProducts.replaceWith($newProducts);

        var pairs = [
            '.woocommerce-result-count',
            '.woocommerce-ordering'
        ];

        pairs.forEach(function(selector) {
            var $old = $(selector).first();
            var $new = $page.find(selector).first();

            if ($old.length && $new.length) {
                $old.replaceWith($new);
            }
        });

        var $oldPagination = $('.woocommerce-pagination').first();
        var $newPagination = $page.find('.woocommerce-pagination').first();

        if ($oldPagination.length && $newPagination.length) {
            $oldPagination.replaceWith($newPagination);
        } else if ($oldPagination.length) {
            $oldPagination.remove();
        } else if ($newPagination.length) {
            $('.products').last().after($newPagination);
        }

        var title = $page.find('title').text();
        if (title) {
            document.title = title;
        }

        var $newH1 = $page.find('h1').first();
        var $oldH1 = $('h1').first();

        if ($newH1.length && $oldH1.length) {
            $oldH1.html($newH1.html());
        }

        $(document.body).trigger('updated_wc_div');
        $(document).trigger('wdShopPageInit');
        $(window).trigger('resize');

        return true;
    }

    $(window).on('elementor/frontend/init', function() {
        if (
            typeof elementorFrontend !== 'undefined' &&
            elementorFrontend.hooks
        ) {
            elementorFrontend.hooks.addAction(
                'frontend/element_ready/spf_smart_filter.default',
                function($scope) {
                    var $wrap = $scope.find('.spf-wrap');

                    if ($wrap.length && !$wrap.data('spf-init')) {
                        $wrap.data('spf-init', true);
                        initFilter($wrap);
                    }
                }
            );
        }
    });

})(jQuery);
