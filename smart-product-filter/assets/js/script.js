(function($) {
    'use strict';

    function initFilter($wrap) {
        var $minRange = $wrap.find('.spf-range-min');
        var $maxRange = $wrap.find('.spf-range-max');
        var $range    = $wrap.find('.spf-slider-range');
        var $from     = $wrap.find('.spf-price-from');
        var $to       = $wrap.find('.spf-price-to');
        var absMin    = parseFloat($wrap.data('min')) || 0;
        var absMax    = parseFloat($wrap.data('max')) || 10000000;
        var step      = parseFloat($wrap.data('step')) || 1;

        // ============ اسلایدر قیمت ============
        function formatPrice(n) {
            return Number(n).toLocaleString('fa-IR');
        }

        function updateSlider() {
            var minVal = parseFloat($minRange.val());
            var maxVal = parseFloat($maxRange.val());
            if (minVal > maxVal - step) { minVal = maxVal - step; $minRange.val(minVal); }
            var range = absMax - absMin;
            var leftPct  = ((minVal - absMin) / range) * 100;
            var rightPct = 100 - ((maxVal - absMin) / range) * 100;
            $range.css({ left: leftPct + '%', right: rightPct + '%' });
            $from.text(formatPrice(minVal));
            $to.text(formatPrice(maxVal));
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

        // ============ اعمال خودکار قیمت (با تاخیر) ============
        var priceTimer;
        $minRange.add($maxRange).on('input', function() {
            clearTimeout(priceTimer);
            priceTimer = setTimeout(function() {
                applyFilter($wrap);
            }, 600); // 600 میلی‌ثانیه بعد از توقف حرکت
        });

        // ============ ساخت URL ============
        function buildFilterUrl($wrap) {
    var url = new URL(window.location.href);
    var cats = $wrap.find('.spf-cat-checkbox:checked').map(function() {
        return this.value;
    }).get();
    var minVal = parseFloat($wrap.find('.spf-range-min').val());
    var maxVal = parseFloat($wrap.find('.spf-range-max').val());
    var absMin = parseFloat($wrap.data('min'));
    var absMax = parseFloat($wrap.data('max'));

    // پاک کردن پارامترهای قبلی
    ['spf_cats', 'spf_min_price', 'spf_max_price', 'paged'].forEach(function(p) {
        url.searchParams.delete(p);
    });

    // ==========================================
    // 🔑 نکته کلیدی: به جای تغییر path، از query استفاده می‌کنیم
    // ولی این بار مطمئن می‌شیم وودمارت هم می‌شناسه
    // ==========================================

    if (cats.length) {
        url.searchParams.set('spf_cats', cats.join(','));
    }
    if (minVal > absMin) {
        url.searchParams.set('spf_min_price', minVal);
    }
    if (maxVal < absMax) {
        url.searchParams.set('spf_max_price', maxVal);
    }

    return url;
}

        // ============ پیدا کردن ظرف محصولات ============
        function findProductsContainer() {
            var selectors = [
                'ul.products',
                '.products.columns-4',
                '.products.columns-3',
                '.products.columns-2',
                '.products',
                '.elementor-loop-container',
                '.elementor-widget-loop-grid .elementor-loop-container',
                '.wd-products-element .products',
                '.wd-products',
                '.woocommerce-products',
                '.shop-container .products'
            ];
            for (var i = 0; i < selectors.length; i++) {
                if ($(selectors[i]).length) {
                    return selectors[i];
                }
            }
            return null;
        }

        // ============ اعمال فیلتر با AJAX ============
        var ajaxTimer;
        function applyFilter($wrap) {
            clearTimeout(ajaxTimer);
            var newUrl = buildFilterUrl($wrap);
            var targetSelector = findProductsContainer();

            if (!targetSelector) {
                window.location.href = newUrl.toString();
                return;
            }

            // نمایش لودر
            $wrap.addClass('spf-loading');
            $(targetSelector).first().addClass('spf-products-loading');

            ajaxTimer = setTimeout(function() {
                $.ajax({
                    url: newUrl.toString(),
                    type: 'GET',
                    dataType: 'html',
                    success: function(response) {
                        var $newDoc = $(response);
                        var $newProducts = $newDoc.find(targetSelector).first();

                        if ($newProducts.length) {
                            $(targetSelector).first().replaceWith($newProducts);
                            $(document.body).trigger('updated_wc_div');
                            window.history.pushState({}, '', newUrl.toString());

                            // اسکرول نرم
                            var $container = $(targetSelector).first();
                            if ($container.length) {
                                $('html, body').animate({
                                    scrollTop: $container.offset().top - 100
                                }, 300);
                            }
                        } else {
                            window.location.href = newUrl.toString();
                        }
                    },
                    error: function() {
                        window.location.href = newUrl.toString();
                    },
                    complete: function() {
                        $wrap.removeClass('spf-loading');
                        $(targetSelector).first().removeClass('spf-products-loading');
                    }
                });
            }, 200); // تاخیر کوچک برای جمع کردن کلیک‌ها
        }

        // ============ رویداد چک‌باکس دسته ============
        $wrap.on('change', '.spf-cat-checkbox', function() {
            applyFilter($wrap);
        });

        // ============ باز/بسته کردن زیرشاخه‌ها ============
        $wrap.on('click', '.spf-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $toggle = $(this);
            var $li = $toggle.closest('li');
            var $children = $li.children('.spf-cat-children, .spf-cat-grandchildren');

            if ($children.length) {
                $children.slideToggle(200);
                $toggle.toggleClass('open');
            }
        });

        // ============ حذف فیلترها (با کلیک راست یا دکمه مخفی) ============
        // دکمه ریست رو نگه می‌داریم ولی مخفی می‌کنیم
        $wrap.find('.spf-reset').on('click', function(e) {
            e.preventDefault();
            $wrap.find('.spf-cat-checkbox').prop('checked', false);
            $minRange.val(absMin);
            $maxRange.val(absMax);
            updateSlider();

            var url = new URL(window.location.href);
            url.searchParams.delete('spf_cats');
            url.searchParams.delete('spf_min_price');
            url.searchParams.delete('spf_max_price');
            url.searchParams.delete('paged');
            window.history.pushState({}, '', url.toString());
            applyFilter($wrap);
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
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/spf_smart_filter.default', function($scope) {
                var $wrap = $scope.find('.spf-wrap');
                if ($wrap.length && !$wrap.data('spf-init')) {
                    $wrap.data('spf-init', true);
                    initFilter($wrap);
                }
            });
        }
    });

})(jQuery);