(function($) {
    'use strict';

    function initFilter($wrap) {

        var $minRange = $wrap.find('.spf-range-min');
        var $maxRange = $wrap.find('.spf-range-max');
        var $range    = $wrap.find('.spf-slider-range');
        var $from     = $wrap.find('.spf-price-from');
        var $to       = $wrap.find('.spf-price-to');

        var absMin = parseFloat($wrap.data('min')) || 0;
        var absMax = parseFloat($wrap.data('max')) || 10000000;
        var step   = parseFloat($wrap.data('step')) || 1;

        function formatPrice(n) {
            return Number(n).toLocaleString('fa-IR');
        }

        function updateSlider() {
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
                min: parseFloat($minRange.val()) || absMin,
                max: parseFloat($maxRange.val()) || absMax
            };
        }

        function getTermUrlBySlug(slug) {
            var $checkbox = $wrap.find('.spf-cat-checkbox').filter(function() {
                return this.value === slug;
            }).first();

            return $checkbox.attr('data-term-url') || '';
        }

        /**
         * URL مرورگر:
         * - یک دسته: canonical term URL ووکامرس، بنابراین hierarchy والد/فرزند حفظ می‌شود.
         * - چند دسته: shop/?product_cat=cat-a,cat-b
         * - قیمت: min_price/max_price استاندارد WooCommerce.
         */
        function buildBrowserUrl() {

            var cats = getSelectedCategories();
            var price = getPriceValues();
            var url;

            if (cats.length === 1) {

                var termUrl = getTermUrlBySlug(cats[0]);

                if (termUrl) {
                    url = new URL(termUrl, window.location.origin);
                } else {
                    url = new URL(spfData.shopUrl, window.location.origin);
                }

            } else {

                url = new URL(spfData.shopUrl, window.location.origin);

                if (cats.length > 1) {
                    url.searchParams.set('product_cat', cats.join(','));
                }
            }

            [
                'spf_cats',
                'spf_min_price',
                'spf_max_price',
                'paged'
            ].forEach(function(param) {
                url.searchParams.delete(param);
            });

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

            return url;
        }

        function findProductsContainer() {

            var selectors = [
                '.site-content ul.products',
                '.main-page-wrapper ul.products',
                '.shop-content ul.products',
                '.shop-container ul.products',
                'ul.products'
            ];

            for (var i = 0; i < selectors.length; i++) {
                var $found = $(selectors[i]);

                if ($found.length) {
                    return $found.first();
                }
            }

            return $();
        }

        function applyFilter($wrap) {

            var cats = getSelectedCategories();
            var price = getPriceValues();
            var $products = findProductsContainer();

            if (!$products.length) {
                window.location.href = buildBrowserUrl().toString();
                return;
            }

            $wrap.addClass('spf-loading');
            $products.addClass('spf-products-loading');

            $.ajax({
                url: spfData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'spf_filter',
                    nonce: spfData.nonce,
                    cats: cats,
                    min: price.min > absMin ? price.min : 0,
                    max: price.max < absMax ? price.max : 0,
                    paged: 1
                }
            }).done(function(response) {

                if (
                    response &&
                    response.success &&
                    response.data &&
                    typeof response.data.html !== 'undefined'
                ) {

                    var $newProducts = $(response.data.html).filter('.products');

                    if (!$newProducts.length) {
                        $newProducts = $(response.data.html).find('.products').first();
                    }

                    if ($newProducts.length) {

                        $products.replaceWith($newProducts.first());

                        $(document.body).trigger('updated_wc_div');

                        window.history.pushState(
                            {
                                spf: true,
                                cats: cats,
                                min: price.min,
                                max: price.max
                            },
                            '',
                            buildBrowserUrl().toString()
                        );

                    } else {
                        window.location.href = buildBrowserUrl().toString();
                    }

                } else {
                    window.location.href = buildBrowserUrl().toString();
                }

            }).fail(function() {

                window.location.href = buildBrowserUrl().toString();

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
                applyFilter($wrap);
            }, 600);
        });

        $wrap.on('change', '.spf-cat-checkbox', function() {
            applyFilter($wrap);
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
