<?php

defined( 'ABSPATH' ) || exit;

// Add a menu item for the plugin in the WooCommerce admin menu
add_action( 'admin_menu', 'wc_papr_add_admin_menu', 99 );
function wc_papr_add_admin_menu(): void {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	add_submenu_page(
		'woocommerce',
		'Product Attributes Payment Restrictions',
		'Product Attributes Payment Restrictions',
		'manage_options',
		'wc-papr-settings',
		'wc_papr_settings_page',
	);
}

// Enqueue admin scripts for the settings page to use SelectWoo
add_action( 'admin_enqueue_scripts', 'wc_papr_enqueue_admin_scripts' );
function wc_papr_enqueue_admin_scripts( $hook_suffix ): void {
	// Enqueue script only on the settings page
	if ( $hook_suffix === 'woocommerce_page_wc-papr-settings' ) {
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		wp_enqueue_script( 'wc_papr', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'jquery' ), WC_PAPR_PLUGIN_VERSION, true );
	}
}

// Display the settings page
function wc_papr_settings_page(): void {
	// TODO Use WordPress Settings API
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Save settings if the form is submitted
	$save_successfully = false;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		if ( isset( $_POST['wc_papr_product_attributes'] ) && $selected_attributes = $_POST['wc_papr_product_attributes'] ) {
			foreach ( $selected_attributes as $attribute ) {
				if ( ! taxonomy_exists( 'pa_' . $attribute ) ) {
					wp_die( 'Invalid attribute' );
				}
			}

			// Save the data in the database
			update_option( 'wc_papr_product_attributes', maybe_serialize( $selected_attributes ) );

			if (isset($_POST['wc_papr_restrict_variations'])) {
				update_option( 'wc_papr_restrict_variations', '1');
			} else {
				delete_option( 'wc_papr_restrict_variations' );
			}

			if (isset($_POST['wc_papr_show_site_wide_notice'])) {
				update_option( 'wc_papr_show_site_wide_notice', '1');
			} else {
				delete_option( 'wc_papr_show_site_wide_notice' );
			}

			if (isset($_POST['wc_papr_show_variations_notice'])) {
				update_option( 'wc_papr_show_variations_notice', '1');
			} else {
				delete_option( 'wc_papr_show_variations_notice' );
			}
		} else {
			delete_option( 'wc_papr_product_attributes' );
		}

		$save_successfully = true;
	}

	// Retrieve all product attributes
	$product_attributes = wc_get_attribute_taxonomies();

	// Get the currently saved attributes (if any)
	$selected_attributes = maybe_unserialize( get_option( 'wc_papr_product_attributes' ) );

	// Display the settings form
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( $save_successfully ) { ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html__( 'Settings saved successfully.', 'wc_papr' ); ?></p>
			</div>
		<?php } ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wc-papr-settings' ) ); ?>">
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="wc-papr-product-attributes"><?php echo esc_html__( 'Product attributes to be configured', 'wc_papr' ) ?></label>
						</th>
						<td>
							<select name="wc_papr_product_attributes[]" id="wc-papr-product-attributes"
							        placeholder="data-placeholder=<?php echo esc_attr__( 'Select product attributes', 'wc_papr' ); ?>"
							        style="width: 100%" multiple>
								<?php
								foreach ( $product_attributes as $attribute ) {
									$attribute_name = $attribute->attribute_name;
									if ( is_array( $selected_attributes ) && in_array( $attribute_name, $selected_attributes ) ) {
										$selected = 'selected';
									} else {
										$selected = '';
									}
									echo '<option value="' . esc_attr( $attribute_name ) . '" ' . $selected . '>' . esc_html( $attribute->attribute_label ) . '</option>';
								}
								?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Select product attributes that you would like to configure to be compatible with specific payment methods.
								 After this selection, you can configure the selected attribute\'s terms in the Attributes page to specify which payment methods are
								  compatible with each term.', 'wc_papr' ) ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__('Variations restrictions and notices', 'wc_papr') ?></h2>
			<hr>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__('Restrict variations', 'wc_papr') ?></th>
						<td>
							<input name="wc_papr_restrict_variations" id="wc-papr-restrict-variations" type="checkbox"
							       value="1" <?php checked( get_option( 'wc_papr_restrict_variations' ), '1' ); ?>>
							<label for="wc-papr-restrict-variations">
								<?php echo esc_html__( 'Restrict variations options according to cart content', 'wc_papr' ) ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'If enabled, the plugin will restrict the variations options displayed for a product according to the
									cart content. For example, if the cart contains a product with the color "red" and another product with the color "blue", the
									variations options for the color attribute will only contain the values "red" and "blue" when trying to add a new product to the cart.'
									, 'wc_papr' ) ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Notices', 'wc_papr') ?></th>
						<td>
							<input name="wc_papr_show_site_wide_notice" id="wc-papr-show-site-wide-notice" type="checkbox"
							       value="1" <?php checked( get_option( 'wc_papr_show_site_wide_notice' ), '1' ); ?>>
							<label for="wc-papr-show-site-wide-notice">
								<?php echo esc_html__( 'Show a site wide notice', 'wc_papr' ) ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'If enabled, the plugin will show a site wide notice if there are products in the cart that restrict
								the available payment methods.', 'wc_papr' ) ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<input name="wc_papr_show_variations_notice" id="wc-papr-show-options-notice" type="checkbox"
							       value="1" <?php checked( get_option( 'wc_papr_show_variations_notice' ), '1' ); ?>>
							<label for="wc-papr-show-options-notice">
								<?php echo esc_html__( 'Show a notice before a product options', 'wc_papr' ) ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'If enabled, the plugin will show a notice before the product options if there are products in the
								cart that restrict the available payment methods.', 'wc_papr' ) ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save settings', 'wc_papr' ) ); ?>
		</form>
	</div>
	<?php
}

// Get the saved attributes (if any)
$selected_attributes = maybe_unserialize( get_option( 'wc_papr_product_attributes' ) );

if ( $selected_attributes ) {
	// We add filters and actions for each configured attribute's page, so we can select the compatible payment methods and display previous saved data
	foreach ( $selected_attributes as $attribute ) {
		// Add a custom column to the product attribute's terms list
		add_filter( 'manage_edit-pa_' . $attribute . '_columns', 'wc_papr_add_payment_method_column_header' );

		// Populate the custom column with data
		add_action( 'manage_pa_' . $attribute . '_custom_column', 'wc_papr_populate_payment_method_column_data', 10, 3 );

		// Allows for the selection of compatible payment methods when adding a new term
		add_action( 'pa_' . $attribute . '_add_form_fields', 'wc_papr_edit_term_add_payment_field' );

		// Add the payment method selection to the product attribute's terms edit page
		add_action( 'pa_' . $attribute . '_edit_form_fields', 'wc_papr_edit_term_add_payment_field' );

		// Save the payment methods selected when adding a new term
		add_action( 'created_pa_' . $attribute, 'wc_papr_save_term_payment_methods' );

		// Save the payment methods selected for the term
		add_action( 'edited_pa_' . $attribute, callback: 'wc_papr_save_term_payment_methods' );
	}
}

function wc_papr_add_payment_method_column_header( $columns ) {
	$columns['wp_papr_payment_methods'] = esc_html__( 'Compatible payment methods', 'wc_papr' );

	return $columns;
}

function wc_papr_populate_payment_method_column_data( $custom_column, $column_name, $term_id ): void {
	if ( $column_name === 'wp_papr_payment_methods' ) {
		$payment_methods            = WC()->payment_gateways->get_available_payment_gateways();
		$compatible_payment_methods = '';

		// Get the saved payment methods for this term
		$term_payment_methods = get_term_meta( $term_id, '_wc_papr_payment_methods', true );
		$term_payment_methods = $term_payment_methods ? maybe_unserialize( $term_payment_methods ) : [];

		foreach ( $payment_methods as $method ) {
			if ( $term_payment_methods && in_array( $method->id, $term_payment_methods, true ) ) {
				$compatible_payment_methods .= '<span style="background: rgba(204,204,204,0.36); border-radius: 5px; padding: 3px; margin: 3px; display: inline-block">'
				                               . $method->title . '</span>';
			}
		}

		echo $compatible_payment_methods;
	}
}

function wc_papr_edit_term_add_payment_field( $term ): void {
	if ( is_object( $term ) ) {
		// We're editing a term, so we get its configured payment methods
		$term_payment_methods = get_term_meta( $term->term_id, '_wc_papr_payment_methods', true );
		$term_payment_methods = $term_payment_methods ? maybe_unserialize( $term_payment_methods ) : [];
	} else {
		$term_payment_methods = [];
	}

	$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
	?>
	<tr class="form-field">
		<th scope="row">
			<label for="wc-papr-payment-methods"><?php echo esc_html__( 'Payment methods compatible with this term', 'wc_papr' ); ?></label>
		</th>

		<td>
			<select id="wc-papr-payment-methods" name="allowed_payment_methods[]"
			        data-placeholder="<?php echo esc_attr__( 'Select payment methods', 'wc_papr' ); ?>" style="width: 100%" multiple>
				<?php
				if ( $payment_methods ) {
					foreach ( $payment_methods as $method ) {
						?>
						<option value="<?php echo esc_attr( $method->id ); ?>"
							<?php if ( $term_payment_methods && in_array( $method->id, $term_payment_methods, true ) ) {
								echo 'selected';
							} ?>
						><?php echo esc_html( $method->title ); ?></option>
						<?php
					}
				}
				?>
			</select>
			<p class="description">
				<?php echo esc_html__( 'Select compatible payment methods for the products that use this term.', 'wc_papr' ); ?>
			</p>
		</td>
	</tr>
	<script>
		(function ($) {
			$(document).ready(function () {
				// Initialize SelectWoo for the select field
				$('#wc-papr-payment-methods').selectWoo();
			});
		})(jQuery);
	</script>
	<?php
}

function wc_papr_save_term_payment_methods( $term_id ): void {
	if ( isset( $_POST['allowed_payment_methods'] ) ) {
		$payment_methods = WC()->payment_gateways->get_available_payment_gateways();

		$allowed_payment_methods = array_intersect( $_POST['allowed_payment_methods'], array_keys( $payment_methods ) );

		update_term_meta( $term_id, '_wc_papr_payment_methods', maybe_serialize( $allowed_payment_methods ) );
	} else {
		delete_term_meta( $term_id, '_wc_papr_payment_methods' );
	}
}