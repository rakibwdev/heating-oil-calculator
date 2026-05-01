jQuery(document).ready(function($) {
    // Selectors
    const selectors = {
        standard: {
            postalCode: '#postal_code', liters: '#liters', deliveryPoints: '#delivery_points'
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
        let postalCode = $(selectors.elementor.postalCode).val() || $(selectors.standard.postalCode).val() || "";
        let liters = $(selectors.elementor.liters).val() || $(selectors.standard.liters).val() || "";
        let deliveryPoints = $(selectors.elementor.deliveryPoints).val() || $(selectors.standard.deliveryPoints).val() || "1";
        return { postalCode, liters, deliveryPoints };
    }

    function validateInputs(postalCode, liters) {
        const $container = $('.calc-container');

const $errorMsg = $container.find('.error_messhi');
const $btn = $container.find(selectors.checkoutBtn);
        let errors = [];
        
        if (!/^\d{5}$/.test(postalCode)) errors.push('Bitte geben Sie eine gültige 5-stellige Postleitzahl ein.');
        const litersNum = parseFloat(liters);
        if (isNaN(litersNum) || litersNum < 1500 || litersNum > 6000) errors.push('Die Liefermenge muss zwischen 1500 und 6000 Litern liegen.');
        
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
        validateInputs(postalCode, liters);

        // We calculate if zip is 5 digits, even if liters are out of range (for live feedback)
        if (!/^\d{5}$/.test(postalCode) || !liters) return;

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
            let match = cls.match(/(?:post|e-loop-item)-(\d+)/);
            if (match) return match[1];
        }
        return null;
    }

    function performCalculation(productId, postalCode, liters, deliveryPoints, callback) {
        $.ajax({
            url: hoc_ajax.ajax_url, type: 'POST',
            data: {
                action: 'calculate_heating_oil_price',
                nonce: hoc_ajax.nonce,
                postal_code: postalCode,
                liters: liters,
                delivery_points: deliveryPoints,
                product_id: productId
            },
            success: function(response) {
                if (response.success && callback) callback(response.data);
            }
        });
    }

    function updateLoopItem($item, data, postalCode, liters, deliveryPoints) {
        $item.find('.priceStandard .elementor-heading-title, .priceStandard h2').text('Gesamtpreis: €' + data.total_price);
        
        const productId = getProductIdFromItem($item);
        const homeUrl = hoc_ajax.home_url;
        const queryParams = $.param({
            'add-to-cart': productId, 'quantity': 1,
            'hoc_liters': liters, 'hoc_delivery_points': deliveryPoints,
            'hoc_postal_code': postalCode, 'hoc_total_price': data.total_price_raw
        });
        
        const $button = $item.find('.go_checkout').length ? $item.find('.go_checkout') : $item.find('.elementor-button-link');
        $button.attr('href', homeUrl + '?' + queryParams);
    }

    function updateSingleProduct(data, postalCode) {
        $('#total_price').text('€' + data.total_price);
        $('#price_per_100l').text('€' + data.price_per_100l);
        $('#delivery_surcharge').text('€' + data.delivery_surcharge);
        
        $('#hoc_liters').val(data.liters);
        $('#hoc_delivery_points').val(data.delivery_points);
        $('#hoc_postal_code').val(postalCode);
        $('#hoc_total_price').val(data.total_price_raw);
        $('.error-messages').hide();
    }

    // Event Listeners
    const inputSelectors = '#form-field-zip, #form-field-liters, #postal_code, #liters';
    const selectSelectors = '#form-field-del_points, #delivery_points';

    $(document).on('click', selectors.checkoutBtn, function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const $item = $btn.closest('[data-elementor-type="loop-item"]');
        const productId = getProductIdFromItem($item) || hoc_ajax.product_id;
        const { postalCode, liters, deliveryPoints } = getInputValues();
        
        if (!validateInputs(postalCode, liters)) return false;

        const homeUrl = hoc_ajax.home_url;
        const queryParams = $.param({
            'add-to-cart': productId,
            'quantity': 1,
            'hoc_liters': liters,
            'hoc_delivery_points': deliveryPoints,
            'hoc_postal_code': postalCode
        });

        window.location.href = homeUrl + '?' + queryParams;
    });

    let debounceTimer;
    $(document).on('input', inputSelectors, function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(calculatePrices, 250);
    });

    $(document).on('change', selectSelectors, function() {
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

    calculatePrices(); // Initial load
});
