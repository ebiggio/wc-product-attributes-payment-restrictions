<?php

defined( 'ABSPATH' ) || exit;

class WC_PAPR_Front_Function_Handler {
	private array $wc_papr_settings;
	private array $configured_attributes;
	private array|null $cart_variations_with_payment_restrictions = null;

	public function __construct() {
		// Load the plugin settings
		$this->wc_papr_settings      = maybe_unserialize( get_option( 'wc_papr_settings', array() ) );
		$this->configured_attributes = $this->wc_papr_settings['wc_papr_product_attributes'] ?? array();

		// Add filters and actions if the corresponding option is enabled
		if ( $this->configured_attributes ) {
			if ( isset( $this->wc_papr_settings['wc_papr_restrict_variations'] ) ) {
				add_filter( 'woocommerce_dropdown_variation_attribute_options_args', array( $this, 'wc_papr_customize_variation_dropdown_options' ) );
			}

			if ( isset( $this->wc_papr_settings['wc_papr_show_site_wide_notice'] ) ) {
				add_action( 'wp_footer', array( $this, 'wc_papr_show_site_wide_notice' ) );
			}

			if ( isset( $this->wc_papr_settings['wc_papr_show_variations_notice'] ) ) {
				add_action( 'woocommerce_before_variations_form', array( $this, 'wc_papr_show_variations_form_notice' ) );
			}

			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'wc_papr_filter_payment_methods_by_product_attribute' ) );
		}
	}

	// Get the payment methods configured for a term
	private function get_term_payment_methods( $attribute_name, $attribute_value ) {
		// We get the term object, since we need the term ID to get the payment methods configured for the term
		$term = get_term_by( 'slug', $attribute_value, $attribute_name );

		// We get the payment methods configured for the term
		$term_payment_methods = get_term_meta( $term->term_id, '_wc_papr_payment_methods', true );

		// If the term has payment methods configured, we return them
		if ( $term_payment_methods ) {
			return maybe_unserialize( $term_payment_methods );
		}

		return array();
	}

	// Checks the products that are in the cart and returns the variation attributes that are configured to have payment methods restrictions
	private function get_cart_variation_attributes_with_payment_restrictions(): array {
		/*
		 * We use the attribute $this->cart_variations_with_payment_restrictions as a sort of cache, so we don't have to process the cart contents every time.
		 * If it's null, it means that we haven't processed the cart contents yet, so we do it now. Otherwise, we return its value, which can be an empty array
		 * if there are no product variations with payment methods restrictions in the cart (or if the cart is empty)
		 */
		if ( ! is_null( $this->cart_variations_with_payment_restrictions ) ) {
			return $this->cart_variations_with_payment_restrictions;
		}

		$cart_variations_with_payment_restrictions = array();

		// We iterate through the cart items to get the product attributes
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// We check if the product is a variation, since we're only interested in those
			if ( $cart_item['variation_id'] ) {
				$product_attributes = wc_get_product( $cart_item['variation_id'] )->get_attributes();
			} else {
				continue;
			}

			// We iterate through the product attributes to get the configured ones
			foreach ( $product_attributes as $attribute_name => $attribute_value ) {
				// We check if we haven't already added the attribute to the result array. If we have, we skip it
				if ( isset( $cart_variations_with_payment_restrictions[ $attribute_name ][ $attribute_value ] ) ) {
					continue;
				}

				// If the attribute is configured to have payment methods restrictions (remember that the attribute name is prefixed with "pa_")
				if ( in_array( substr( $attribute_name, 3 ), $this->configured_attributes ) ) {
					if ( $this->get_term_payment_methods( $attribute_name, $attribute_value ) ) {
						$cart_variations_with_payment_restrictions[ $attribute_name ] = $attribute_value;
					}
				}
			}
		}

		$this->cart_variations_with_payment_restrictions = $cart_variations_with_payment_restrictions;

		return $this->cart_variations_with_payment_restrictions;
	}

	// Filter the payment methods to remove the ones that are not configured for the product attributes in the cart
	public function wc_papr_filter_payment_methods_by_product_attribute( $payment_methods ) {
		// We iterate through the cart items to get the product attributes
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// We check if the product is a variation, since we're only interested in those
			if ( $cart_item['variation_id'] ) {
				$product_attributes = wc_get_product( $cart_item['variation_id'] )->get_attributes();
			} else {
				continue;
			}

			// We iterate through the product attributes to get the configured ones
			foreach ( $product_attributes as $attribute_name => $attribute_value ) {
				// If the attribute is configured to have payment methods restrictions (remember that the attribute name is prefixed with "pa_")
				if ( in_array( substr( $attribute_name, 3 ), $this->configured_attributes ) ) {
					if ( $term_payment_methods = $this->get_term_payment_methods( $attribute_name, $attribute_value ) ) {
						// We iterate through the payment methods to remove the ones that are not configured for the term
						foreach ( $payment_methods as $gateway_id => $payment_method ) {
							if ( ! in_array( $gateway_id, $term_payment_methods ) ) {
								unset( $payment_methods[ $gateway_id ] );
							}
						}
					}
				}
			}
		}

		return $payment_methods;
	}

	// Filter the product variations dropdown options to leave only the ones that are configured for the product attributes that are already in the cart
	public function wc_papr_customize_variation_dropdown_options( $args ) {
		// We check if the product attributes in the cart have payment methods restrictions. If they don't, we return the original $args
		if ( ! $this->get_cart_variation_attributes_with_payment_restrictions() ) {
			return $args;
		}

		/*
		 * We check if the variation attribute has already been added to the cart from another product. If it has, we leave only the selected values that
		 * currently exist in the cart, removing the rest
		 */
		if ( isset( $this->cart_variations_with_payment_restrictions[ $args['attribute'] ] ) ) {
			$args['selected'] = $this->cart_variations_with_payment_restrictions[ $args['attribute'] ];

			foreach ( $args['options'] as $key => $option ) {
				if ( $option === $this->cart_variations_with_payment_restrictions[ $args['attribute'] ] ) {
					continue;
				}

				unset( $args['options'][ $key ] );
			}
		}

		return $args;
	}

	// Show a site-wide notice if the product attributes in the cart have payment methods restrictions
	public function wc_papr_show_site_wide_notice(): void {
		/*
		 * We check if the product attributes in the cart have payment methods restrictions. If they don't, we don't show the site-wide notice. We also don't
		 * show the notice if the user is in the cart, checkout or account pages
		 */
		if ( ! $this->get_cart_variation_attributes_with_payment_restrictions() || is_cart() || is_checkout() || is_account_page() ) {
			return;
		}

		// We also won't show the notice if the user is viewing a single product page and the option to show a notice in the variations form is enabled
		if ( is_product() && isset( $this->wc_papr_settings['wc_papr_show_variations_notice'] ) ) {
			return;
		}

		if ( ! empty( $this->cart_variations_with_payment_restrictions ) ) {
			$notice = __( 'There are products in the cart with payment method restrictions. Please keep in mind that selecting an option that is not compatible with those selected for the products in the cart may result no payment methods being available at checkout.'
				, 'wc-papr' );
			echo apply_filters( 'woocommerce_demo_store'
				, '<p class="woocommerce-wc-papr-notice demo_store">' . wp_kses_post( $notice ) . '</p>', $notice );
		}
	}

	// Show a notice in the variations form if the product attributes in the cart have payment methods restrictions
	public function wc_papr_show_variations_form_notice(): void {
		// We check if the product attributes in the cart have payment methods restrictions. If they don't, we don't show the notice
		if ( ! $this->get_cart_variation_attributes_with_payment_restrictions() ) {
			return;
		}

		if ( ! empty( $this->cart_variations_with_payment_restrictions ) ) {
			wc_print_notice( __( 'There are products in the cart with payment method restrictions. Please keep in mind that selecting an option that is not compatible with those selected for the products in the cart may result no payment methods being available at checkout.'
				, 'wc-papr' ), 'notice' );
		}
	}
}