jQuery(document).ready(function($) {
    // Selectors for both standard form and Elementor form
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
            form: '.elementor-form'
        }
    };

    // Helper to get input values from whichever form is present
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

    // Main calculation function
    function calculatePrices() {
        const { postalCode, liters, deliveryPoints } = getInputValues();

        if (!postalCode || !liters || !deliveryPoints) {
            return;
        }

        // Check if we are in a loop or single product page
        const loopItems = $('[data-elementor-type="loop-item"]');

        if (loopItems.length > 0) {
            // Update each item in the loop
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
            // Single product page
            performCalculation(hoc_ajax.product_id, postalCode, liters, deliveryPoints, function(data) {
                updateSingleProduct(data, postalCode);
            });
        }
    }

    function getProductIdFromItem($item) {
        // Try to get product ID from classes like post-123 or e-loop-item-123
        const classes = ($item.attr('class') || '').split(/\s+/);
        for (let cls of classes) {
            if (cls.startsWith('post-')) {
                const id = cls.split('-')[1];
                if (!isNaN(id)) return id;
            }
            if (cls.startsWith('e-loop-item-')) {
                const id = cls.split('-')[2];
                if (!isNaN(id)) return id;
            }
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
        // Update elements within the loop item
        $item.find('.priceStandard .elementor-heading-title').text('Gesamtpreis: €' + data.total_price);
        $item.find('.wc_price .woocommerce-Price-amount').html(data.price_per_100l + '<span class="woocommerce-Price-currencySymbol">€</span>');
        
        // Update button links to add to cart and go directly to checkout
        const productId = getProductIdFromItem($item);
        const checkoutUrl = hoc_ajax.checkout_url;
        const queryParams = $.param({
            'add-to-cart': productId,
            'quantity': 1,
            'hoc_liters': liters,
            'hoc_delivery_points': deliveryPoints,
            'hoc_postal_code': postalCode,
            'hoc_total_price': data.total_price_raw
        });
        
        // Target .go_checkout as requested, falling back to .elementor-button-link
        const $button =  $item.find('.go_checkout a.elementor-button-link');
        $button.each(function() {
            $(this).attr('href', checkoutUrl + '?' + queryParams);
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

    // Listeners
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

    // Prevent Elementor form submission if it's just for calculation
    $(document).on('submit', selectors.elementor.form, function(e) {
        if (!$(this).attr('action') || $(this).attr('action') === window.location.href) {
            e.preventDefault();
            calculatePrices();
        }
    });

    // Initial calculation if values are present
    calculatePrices();

    // Price Details Toggle on Checkout
    $(document).on('click', '#price-details-toggle', function() {
        const $panel = $('#price-details-panel');
        $(this).toggleClass('active');
        $panel.slideToggle();
        
        const isVisible = $panel.is(':visible');
        $(this).html(
            (isVisible ? 'Preisdetails verbergen' : 'Preisdetails anzeigen') + 
            ' <svg class="price-toggle-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>'
        );
    });
});
