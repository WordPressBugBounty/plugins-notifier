(function($) {
    /**
     * Fetch checkout data and send it via AJAX
     */
    function fetchCheckoutData() {
        if ($('body').hasClass('woocommerce-order-received')) {
            return; // Do not trigger on the "Thank You" page
        }

        const fieldsData = { action: 'notifier_save_cart_abandonment_data' };
    
        $('input[id^="billing_"], select[id^="billing_"], input[id^="billing-"], select[id^="billing-"], input[id^="shipping_"], select[id^="shipping_"], input[id^="shipping-"], select[id^="shipping-"]').each(function() {
            const fieldName = $(this).attr('id').replace(/[-]/g, '_'); // Normalize dashes to underscores
            if (fieldName && $(this).val()) {
                fieldsData[fieldName] = $(this).val();
            }
        });

        if ($('#email').length && $('#email').val()) {
            fieldsData['billing_email'] = $('#email').val();
        } else if ($('#billing_email').length && $('#billing_email').val()) {
            fieldsData['billing_email'] = $('#billing_email').val();
        } else {
            fieldsData['billing_email'] = ''; // Default to empty string if no email field is present
        }

        fieldsData.security = notifierFrontObj.security;
    
        $.ajax({
            url: notifierFrontObj.ajaxurl,
            type: 'POST',
            data: fieldsData,
            success: function (response) {
                // Handle the response on success
            }
        });
    }

    $(document).ready(function() {
        if ($('.woocommerce-checkout').length > 0) {
            setTimeout(fetchCheckoutData, 800);
            $(document.body).on('updated_checkout', fetchCheckoutData);
            $(document).on(
                'change',
                'input[id^="billing_"], select[id^="billing_"], input[id^="billing-"], select[id^="billing-"], input[id^="shipping_"], select[id^="shipping_"], input[id^="shipping-"], select[id^="shipping-"], #email',
                fetchCheckoutData
            );
        }
    });

})(jQuery);
