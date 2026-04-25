jQuery(document).ready(function($) {
    // Selectors
    const selectors = {
        standard: {
            postalCode: '#postal_code',
            liters: '#liters',
            deliveryPoints: '#delivery_points',
            totalPrice: '#total_price',
            pricePer100l: '#price_per_100l',
            deliverySurcharge: '#delivery_surcharge'
        },
        elementor: {
            postalCode: '#form-field-zip',
            liters: '#form-field-liters',
            deliveryPoints: '#form-field-del_points',
            form: '.elementor-form',
            buttons: '.e-form__buttons'
        },
        checkoutBtn: '.go_checkout, .elementor-button-link'
    };

    // Ensure error message element exists
    if ($('.error_messhi').length === 0) {
        $(selectors.elementor.buttons).after('<h2 class="error_messhi" style="color:red; font-size:16px; margin-top:10px; display:none;"></h2>');
    }

    function getInputValues() {
        let postalCode, liters, deliveryPoints;

        if ($(selectors.elementor.postalCode).length) {
            postalCode = $(selectors.elementor.postalCode).val();
            liters = $(selectors.elementor.liters).val();
            deliveryPoints = $(selectors.elementor.deliveryPoints).val();
        } else {
            postalCode = $(selectors.standard.postalCode).val();
            liters = $(selectors.standard.liters).val();
            deliveryPoints = $(selectors.standard.deliveryPoints).val();
        }

        return { postalCode, liters, deliveryPoints };
    }

    function validateInputs(postalCode, liters) {
        const $errorMsg = $('.error_messhi');
        const $btn = $(selectors.checkoutBtn);
        
        let errors = [];
        
        // Zip validation (must be 5 digits)
        if (!/^\d{5}$/.test(postalCode)) {
            errors.push(' Die Postleitzahl muss 5 Ziffern haben.');
        }
        
        // Liters validation (1500 - 6000)
        const litersNum = parseFloat(liters);
        if (isNaN(litersNum) || litersNum < 1500 || litersNum > 6000) {
            errors.push('Die Liefermenge muss zwischen 1500 und 6000 Litern liegen.');
        }
        
        if (errors.length > 0) {
            $errorMsg.html(errors.join('<br>')).show();
            $btn.css({'opacity': '0.5', 'pointer-events': 'none'}).attr('disabled', 'disabled');
            return false;
        } else {
            $errorMsg.hide();
            $btn.css({'opacity': '1', 'pointer-events': 'auto'}).removeAttr('disabled');
            return true;
        }
    }

    function calculatePrices() {
        const { postalCode, liters, deliveryPoints } = getInputValues();

        // Always validate first
        const isValid = validateInputs(postalCode, liters);
        
        if (!isValid || !deliveryPoints) {
            return;
        }

        // Check loop vs single product
        const loopItems = $('[data-elementor-type="loop-item"]');

        if (loopItems.length > 0) {
            loopItems.each(function() {
                const $item = $(this);
                const productId = getProductIdFromItem($item);
                if (productId) {
                    performCalculation(productId, postalCode, liters, deliveryPoints, function(data) {
                        updateLoopItem($item, data, postalCode, liters, deliveryPoints);
                    });
                }
            });
        } else if (hoc_ajax.product_id > 0) {
            performCalculation(hoc_ajax.product_id, postalCode, liters, deliveryPoints, function(data) {
                updateSingleProduct(data, postalCode);
            });
        }
    }

    function getProductIdFromItem($item) {
        const classes = ($item.attr('class') || '').split(/\s+/);
        for (let cls of classes) {
            if (cls.startsWith('post-')) return cls.split('-')[1];
            if (cls.startsWith('e-loop-item-')) return cls.split('-')[2];
        }
        return null;
    }

    function performCalculation(productId, postalCode, liters, deliveryPoints, callback) {
        $.ajax({
            url: hoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'calculate_heating_oil_price',
                nonce: hoc_ajax.nonce,
                postal_code: postalCode,
                liters: liters,
                delivery_points: deliveryPoints,
                product_id: productId
            },
            success: function(response) {
                if (response.success && callback) {
                    callback(response.data);
                }
            }
        });
    }

    function updateLoopItem($item, data, postalCode, liters, deliveryPoints) {
        $item.find('.priceStandard .elementor-heading-title').text('Gesamtpreis: €' + data.total_price);
        $item.find('.live_price .elementor-heading-title').text(data.total_price);
        $item.find('.wc_price .woocommerce-Price-amount').html(data.price_per_100l + '<span class="woocommerce-Price-currencySymbol">€</span>');
        
        const productId = getProductIdFromItem($item);
        const homeUrl = hoc_ajax.home_url;
        const queryParams = $.param({
            'add-to-cart': productId,
            'quantity': 1,
            'hoc_liters': liters,
            'hoc_delivery_points': deliveryPoints,
            'hoc_postal_code': postalCode,
            'hoc_total_price': data.total_price_raw
        });
        
        const $button = $item.find('.go_checkout').length ? $item.find('.go_checkout') : $item.find('.elementor-button-link');
        $button.each(function() {
            $(this).attr('href', homeUrl + '?' + queryParams);
        });
    }

    function updateSingleProduct(data, postalCode) {
        $(selectors.standard.totalPrice).text('€' + data.total_price);
        $(selectors.standard.pricePer100l).text('€' + data.price_per_100l);
        $(selectors.standard.deliverySurcharge).text('€' + data.delivery_surcharge);
        
        $('#hoc_liters').val(data.liters);
        $('#hoc_delivery_points').val(data.delivery_points);
        $('#hoc_postal_code').val(postalCode);
        $('#hoc_total_price').val(data.total_price_raw);
        $('.error-messages').hide();
    }

    const allInputs = [
        selectors.standard.postalCode, selectors.standard.liters, selectors.standard.deliveryPoints,
        selectors.elementor.postalCode, selectors.elementor.liters, selectors.elementor.deliveryPoints
    ].join(', ');

    let debounceTimer;
    $(document).on('input change', allInputs, function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(calculatePrices, 500);
    });

    $(document).on('click', '#calculate_price', function() {
        calculatePrices();
    });

    $(document).on('submit', selectors.elementor.form, function(e) {
        if (!$(this).attr('action') || $(this).attr('action') === window.location.href) {
            e.preventDefault();
            calculatePrices();
        }
    });

    $(document).on('click', '#price-details-toggle', function() {
        const $panel = $('#price-details-panel');
        $(this).toggleClass('active');
        $panel.slideToggle();
        const isVisible = $panel.is(':visible');
        $(this).html((isVisible ? 'Preisdetails verbergen' : 'Preisdetails anzeigen') + ' <svg class="price-toggle-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>');
    });

    // Run validation on load
    calculatePrices();
});
