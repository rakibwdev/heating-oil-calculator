jQuery(document).ready(function($) {
    if (typeof hoc_ajax === 'undefined') return;

    // --- CONFIG ---
    const selectors = {
        zip: '#form-field-zip',
        liters: '#form-field-liters',
        points: '#form-field-del_points',
        formButtons: '.e-form__buttons',
        checkoutBtn: '.go_checkout, .elementor-button-link',
        billingDate: '#billing_delivery_date_custom',
        billingDateField: '#billing_delivery_date_custom_field',
        billingPhoneCoord: '#billing_delivery_phone_coord',
        billingPhoneCoordField: '#billing_delivery_phone_coord_field',
        billingShipping: 'input[name="billing_shipping_type_custom"]',
        calcContainer: '.calc-container'
    };

    let currentStep = 1;

    // --- UTILITIES ---
    function validateInputs(postalCode, liters) {
        if ($(selectors.calcContainer).length === 0) return true;
        const $btn = $(selectors.checkoutBtn);
        let errors = [];
        
        if ($('.error_messhi').length === 0 && $(selectors.formButtons).length) {
            $(selectors.formButtons).after('<h2 class="error_messhi" style="color:red; font-size:16px; margin-top:10px; display:none;"></h2>');
        }

        if (postalCode && !/^\d{5}$/.test(postalCode)) errors.push('Bitte geben Sie eine gültige 5-stellige Postleitzahl ein.');
        const litersNum = parseFloat(liters);
        if (liters && (isNaN(litersNum) || litersNum < 1500 || litersNum > 6000)) errors.push('Die Liefermenge muss zwischen 1500 und 6000 Litern liegen.');
        
        if (errors.length > 0) {
            $('.error_messhi').html(errors.join('<br>')).show();
            $btn.css({'opacity': '0.5', 'pointer-events': 'none'}).attr('disabled', 'disabled');
            return false;
        } else {
            $('.error_messhi').hide();
            $btn.css({'opacity': '1', 'pointer-events': 'auto'}).removeAttr('disabled');
            return true;
        }
    }

    // --- NAVIGATION ---
    function goToStep(step) {
        const step1Elements = '.billing-card, .hoc-step-1-extra, .woocommerce-shipping-fields';
        $('.hoc-step-container').hide();
        $(step1Elements).hide();
        
        // Custom: Hide standard checkout tables until confirmation
        $('.woocommerce-checkout-review-order-table, .shop_table').hide();

        // Ensure parent container is visible but Step 1 fields are hidden in other steps
        $('.woocommerce-billing-fields').show();

        if (step === 1) {
            $(step1Elements).show();
            $('.woocommerce-billing-fields__field-wrapper').show();
            $('.next-step-btn').text('Weiter zu: Liefertermin →').attr('data-next', '2').show();
            $('.prev-step-btn').hide();
            $('.custom-submit-btn').hide();
        } 
        else if (step === 2) {
            $('#hoc-checkout-step-2').show();
            $('.next-step-btn').text('Weiter zu: Bestellung prüfen →').attr('data-next', '3').show();
            $('.prev-step-btn').attr('data-prev', '1').show();
            $('.custom-submit-btn').hide();
            generateDeliveryDates();
        } 
        else if (step === 3) {
            $('#hoc-checkout-step-3').show();
            $('.woocommerce-checkout-review-order-table, .shop_table').show(); // Show on final step
            $('.next-step-btn').hide();
            $('.prev-step-btn').attr('data-prev', '2').show();
            $('.custom-submit-btn').show();
        }

        updateProgressBar(step);
        currentStep = step;
        window.scrollTo(0, 0);
    }

    function updateProgressBar(step) {
        $('.hoc-checkout-steps .step-item').removeClass('active completed');
        $('.hoc-checkout-steps .step-line').removeClass('completed');
        $('.hoc-checkout-steps .step-item').each(function(i) {
            const s = i + 1;
            if (s < step) {
                $(this).addClass('completed').find('.step-circle').html('<i class="fas fa-check"></i>');
                $('.hoc-checkout-steps .step-line').eq(i).addClass('completed');
            } else if (s === step) {
                $(this).addClass('active').find('.step-circle').text(s);
            } else {
                $(this).find('.step-circle').text(s);
            }
        });
    }

    function generateDeliveryDates() {
        const $select = $(selectors.billingDate);
        if (!$select.length) return;

        const isExpress = $(selectors.billingShipping + ':checked').val() === 'express';
        const startOffset = isExpress ? 7 : 14;
        
        const currentVal = $select.val();
        $select.empty().append('<option value="">Bitte auswählen</option>');
        
        let date = new Date();
        date.setDate(date.getDate() + startOffset);

        let count = 0;
        const days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        const months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

        while (count < 14) {
            if (date.getDay() !== 0) {
                const label = `${days[date.getDay()]}, ${date.getDate()}. ${months[date.getMonth()]}`;
                const val = date.toISOString().split('T')[0];
                $select.append(`<option value="${val} 08:00-12:00">${label} (08:00 - 12:00)</option>`);
                $select.append(`<option value="${val} 15:00-18:00">${label} (15:00 - 18:00)</option>`);
                count++;
            }
            date.setDate(date.getDate() + 1);
        }
        if (currentVal) $select.val(currentVal);
    }

    // --- CALCULATION LOGIC ---
    function updateComparisonPrices() {
        const zip = $(selectors.zip).val();
        const liters = $(selectors.liters).val();
        const points = $(selectors.points).val() || "1";
        if (!validateInputs(zip, liters)) return;
        if (!/^\d{5}$/.test(zip) || !liters) return;

        $('[data-elementor-type="loop-item"]').each(function() {
            const $item = $(this);
            const productId = $item.attr('class').match(/(?:post|e-loop-item)-(\d+)/)?.[1];
            if (!productId) return;
            $.post(hoc_ajax.ajax_url, {
                action: 'calculate_heating_oil_price',
                nonce: hoc_ajax.nonce,
                postal_code: zip, liters: liters, delivery_points: points, product_id: productId
            }, function(res) {
                if (res.success) {
                    $item.find('.priceStandard .elementor-heading-title, .priceStandard h2').text('Gesamtpreis: €' + res.data.total_price);
                    const url = hoc_ajax.home_url + '?' + $.param({ 'hoc-buy': productId, 'hoc_liters': liters, 'hoc_delivery_points': points, 'hoc_postal_code': zip });
                    $item.find('.go_checkout, .elementor-button-link').attr('href', url);
                }
            });
        });
    }

    // --- LISTENERS ---
    $(document).on('click', '.next-step-btn', function(e) { 
        e.preventDefault(); 
        const next = parseInt($(this).attr('data-next'));
        if (currentStep === 1) {
            let valid = true;
            $('.billing-card input[required], .billing-card select[required]').each(function() {
                if (!$(this).val()) { $(this).css('border-color', 'red'); valid = false; }
                else { $(this).css('border-color', ''); }
            });
            if (!valid) { alert('Bitte füllen Sie alle Pflichtfelder aus.'); return false; }
        }
        if (currentStep === 2 && next === 3) {
            if (!$(selectors.billingDate).val() && !$(selectors.billingPhoneCoord).is(':checked')) {
                // alert('Bitte wählen Sie einen Liefertermin aus oder aktivieren Sie die telefonische Abstimmung.');
                return false;
            }
        }
        goToStep(next); 
    });

    $(document).on('click', '.prev-step-btn', function(e) { e.preventDefault(); goToStep(parseInt($(this).attr('data-prev'))); });
    $(document).on('click', '.custom-submit-btn', function(e) { e.preventDefault(); $('#place_order').click(); });

    $(document).on('change', selectors.billingPhoneCoord, function() {
        $(selectors.billingDate).prop('disabled', $(this).is(':checked'));
        if ($(this).is(':checked')) $(selectors.billingDate).val('');
    });

    $(document).on('input', selectors.zip + ',' + selectors.liters, function() {
        clearTimeout(window.hocTimer);
        window.hocTimer = setTimeout(updateComparisonPrices, 300);
    });

    $(document).on('change', selectors.billingShipping, function() {
        const method = $(this).val();
        $.post(hoc_ajax.ajax_url, {
            action: 'update_checkout_sidebar',
            nonce: hoc_ajax.nonce,
            shipping_method: method
        }, function(res) {
            if (res.success) {
                $('#sidebar').replaceWith(res.data.html);
                // Trigger WooCommerce to update real totals
                $(document.body).trigger('update_checkout');
            }
        });
        
        if (currentStep === 2) {
            generateDeliveryDates();
        }
    });

    $(document).on('change', selectors.points, updateComparisonPrices);
    $(document).on('click', '#price-details-toggle', function() { $('#price-details-panel').slideToggle(); $(this).toggleClass('active'); });

    // INIT
    if ($('.hoc-checkout-main-grid').length) goToStep(1);
    else updateComparisonPrices();
});
