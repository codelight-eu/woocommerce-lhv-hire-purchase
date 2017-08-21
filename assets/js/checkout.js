jQuery(function ($) {

    /**
     * Create a form element with the required bank link data and submit it to LHV
     *
     * @param response
     */
    var sendLHVRequest = function(response) {

        var form = document.createElement("form");

        form.method = "POST";
        form.action = response.url;

        $.each(response.data, function(key, value) {
            var input = document.createElement("input");
            input.name = key;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);

        form.submit();
    };

    var submit_error = function($form, error_message) {
        $( '.woocommerce-error, .woocommerce-message' ).remove();
        $form.prepend( error_message );
        $form.removeClass( 'processing' ).unblock();
        $form.find( '.input-text, select, input:checkbox' ).blur();
        $( 'html, body' ).animate({
            scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
        }, 1000 );
        $( document.body ).trigger( 'checkout_error' );
    };

    /**
     * We are basically hijacking WooCommerce's default submit function to handle the redirect properly.
     */
    $('form.woocommerce-checkout').on('checkout_place_order_lhv_hire_purchase', function(e) {
        var $form = $(e.target);
        $form.addClass( 'processing' );

        var form_data = $form.data();

        if ( 1 !== form_data['blockUI.isBlocked'] ) {
            $form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }

        // ajaxSetup is global, but we use it to ensure JSON is valid once returned.
        $.ajaxSetup( {
            dataFilter: function( raw_response, dataType ) {
                // We only want to work with JSON
                if ( 'json' !== dataType ) {
                    return raw_response;
                }

                try {
                    // Check for valid JSON
                    var data = $.parseJSON( raw_response );

                    if ( data && 'object' === typeof data ) {

                        // Valid - return it so it can be parsed by Ajax handler
                        return raw_response;
                    }

                } catch ( e ) {

                    // Attempt to fix the malformed JSON
                    var valid_json = raw_response.match( /{"result.*"}/ );

                    if ( null === valid_json ) {
                        console.log( 'Unable to fix malformed JSON' );
                    } else {
                        console.log( 'Fixed malformed JSON. Original:' );
                        console.log( raw_response );
                        raw_response = valid_json[0];
                    }
                }

                return raw_response;
            }
        } );

        $.ajax({
            type:		'POST',
            url:		wc_checkout_params.checkout_url,
            data:		$form.serialize(),
            dataType:   'json',
            success:	function( result ) {
                try {
                    if ( result.result === 'success' ) {
                        if (result.redirect) {
                            if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
                                window.location = result.redirect;
                            } else {
                                window.location = decodeURI(result.redirect);
                            }
                        }

                        // assemble form and post to LHV
                        sendLHVRequest(result);

                    } else if ( result.result === 'failure' ) {
                        throw 'Result failure';
                    } else {
                        throw 'Invalid response';
                    }
                } catch( err ) {
                    // Reload page
                    if ( result.reload === 'true' ) {
                        window.location.reload();
                        return;
                    }

                    // Trigger update in case we need a fresh nonce
                    if ( result.refresh === 'true' ) {
                        $( document.body ).trigger( 'update_checkout' );
                    }

                    // Add new errors
                    if ( result.messages ) {
                        submit_error( $form, result.messages );
                    } else {
                        submit_error( $form, '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' );
                    }
                }
            },
            error:	function( jqXHR, textStatus, errorThrown ) {
                submit_error( $form, '<div class="woocommerce-error">' + errorThrown + '</div>' );
            }
        });

        return false;
    });
});