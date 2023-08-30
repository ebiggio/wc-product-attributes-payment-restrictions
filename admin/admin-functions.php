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

// Enqueue admin scripts for the settings page so that we can use Select2 (since SelectWoo is not available in this instance)
add_action( 'admin_enqueue_scripts', 'wc_papr_enqueue_admin_scripts' );
function wc_papr_enqueue_admin_scripts( $hook_suffix ): void {
	// Enqueue script only on the settings page
	if ( $hook_suffix === 'toplevel_page_wc-papr-settings' ) {
		wp_enqueue_script( 'wc_papr', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'jquery' ), WC_PAPR_PLUGIN_VERSION, true );
	}
}

// Display the settings page
function wc_papr_settings_page(): void {
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
            </table>

			<?php submit_button( __('Save settings', 'wc_papr') ); ?>
        </form>
    </div>
	<?php
}

// Adds the payment method selection and saved data to the product attribute's terms defined in the settings page
// Get the saved attributes (if any)
$selected_attributes = maybe_unserialize( get_option( 'wc_papr_product_attributes' ) );

if ( $selected_attributes ) {
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