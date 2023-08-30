<?php

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_available_payment_gateways', 'wc_papr_filter_payment_methods_by_product_attribute' );
function wc_papr_filter_payment_methods_by_product_attribute( $payment_methods ) {
	// Get the saved attributes (if any) configured to have payment methods restrictions. We do this only if we are in the checkout page
	if ( is_checkout() && $configured_attribute = maybe_unserialize( get_option( 'wc_papr_product_attributes' ) ) ) {
		// We iterate through the cart items to get the product attributes
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_attributes = $cart_item['data']->get_attributes();

			// If the product doesn't have any attributes, we skip it
			if ( ! $product_attributes ) {
				continue;
			}

			// We iterate through the product attributes to get the configured ones
			foreach ( $product_attributes as $attribute_name => $attribute_value ) {
				// If the attribute is configured to have payment methods restrictions (remember that the attribute name is prefixed with 'pa_')
				if ( in_array( substr( $attribute_name, 3 ), $configured_attribute ) ) {
					// We get the term object, since we need the term ID to get the payment methods configured for the term
					$term = get_term_by( 'slug', $attribute_value, str_replace( 'attribute_', '', $attribute_name ) );

					// We get the payment methods configured for the term
					$term_payment_methods = get_term_meta( $term->term_id, '_wc_papr_payment_methods', true );

					// If the term has payment methods configured, we filter the available payment methods
					if ( $term_payment_methods ) {
						$term_payment_methods = maybe_unserialize( $term_payment_methods );

						foreach ( $payment_methods as $gateway_id => $payment_method ) {
							if ( ! in_array( $gateway_id, $term_payment_methods ) ) {
								unset( $payment_methods[ $gateway_id ] );
							}
						}
					}
				}
			}
		}
	}

	return $payment_methods;
}