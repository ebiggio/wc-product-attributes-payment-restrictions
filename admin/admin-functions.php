<?php

defined( 'ABSPATH' ) || exit;

class WC_PAPR_Admin_Function_Handler {
	private bool $woocommerce_active = true;
	private array $wc_papr_settings;

	public function __construct() {
		// Load the plugin settings
		$this->wc_papr_settings = maybe_unserialize( get_option( 'wc_papr_settings' ) );

		// Check if WooCommerce is installed and active
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$this->woocommerce_active = false;

			add_action( 'admin_notices', array( $this, 'wc_papr_woocommerce_not_installed_notice' ) );
		}

		// Add a menu item for the plugin in the WooCommerce admin menu
		add_action( 'admin_menu', array( $this, 'wc_papr_add_admin_menu' ), 99 );

		// Enqueue admin scripts for the settings page to use SelectWoo
		add_action( 'admin_enqueue_scripts', array( $this, 'wc_papr_enqueue_admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'wc_papr_register_settings' ) );

		if ( isset( $this->wc_papr_settings['wc_papr_product_attributes'] ) ) {
			$selected_attributes = maybe_unserialize( $this->wc_papr_settings['wc_papr_product_attributes'] );
			// We add filters and actions for each configured attribute's page, so we can select the compatible payment methods and display previous saved data
			foreach ( $selected_attributes as $attribute ) {
				// Add a custom column to the product attribute's terms list
				add_filter( 'manage_edit-pa_' . $attribute . '_columns', 'wc_papr_add_payment_method_column_header' );

				// Populate the custom column with data
				add_action( 'manage_pa_' . $attribute . '_custom_column', 'wc_papr_populate_payment_method_column_data', 10, 3 );

				// Allows for the selection of compatible payment methods when adding a new term
				add_action( 'pa_' . $attribute . '_add_form_fields', 'wc_papr_add_term_add_payment_field' );

				// Add the payment method selection to the product attribute's terms edit page
				add_action( 'pa_' . $attribute . '_edit_form_fields', 'wc_papr_edit_term_add_payment_field' );

				// Save the payment methods selected when adding a new term
				add_action( 'created_pa_' . $attribute, 'wc_papr_save_term_payment_methods' );

				// Save the payment methods selected for the term
				add_action( 'edited_pa_' . $attribute, callback: 'wc_papr_save_term_payment_methods' );
			}
		}
	}

	// Display a notice if WooCommerce is not installed and active
	public function wc_papr_woocommerce_not_installed_notice(): void {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html__( 'WooCommerce Product Attributes Payment Restrictions requires WooCommerce to be installed and active.', 'wc_papr' ); ?></p>
		</div>
		<?php
	}

	// Add a menu item for the plugin in the WooCommerce admin menu
	public function wc_papr_add_admin_menu(): void {
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
			array( $this, 'wc_papr_settings_page' ),
		);
	}

	// Enqueue admin scripts for the settings page to use SelectWoo
	public function wc_papr_enqueue_admin_scripts( $hook_suffix ): void {
		// Enqueue script only on the settings page
		if ( $hook_suffix === 'woocommerce_page_wc-papr-settings' ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );

			// TODO Fix SelectWoo presentation on plugin settings page. It's not showing the placeholder text and the options text is not visible on hover
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script( 'wc_papr', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'jquery' ), WC_PAPR_PLUGIN_VERSION, true );
		}
	}

	// Plugin settings page configuration
	public function wc_papr_register_settings(): void {
		// Register the settings group
		register_setting( 'wc_papr_settings_group', 'wc_papr_settings', array( $this, 'wc_papr_sanitize_settings' ) );

		// Main section of the settings page
		add_settings_section( 'wc-papr-settings-main-section', esc_html__( 'Product attributes', 'wc_papr' )
			, array( $this, 'wc_papr_settings_main_section_callback' ), 'wc_papr_settings_page' );

		// Select field for the product attributes
		add_settings_field( 'wc-papr-product-attributes', esc_html__( 'Product attributes to be configured', 'wc_papr' )
			, array( $this, 'wc_papr_settings_select_options' ), 'wc_papr_settings_page', 'wc-papr-settings-main-section' );

		// Section for the variations restrictions and notices
		add_settings_section( 'wc-papr-settings-restrictions-notices-section', esc_html__( 'Variations restrictions and notices', 'wc_papr' )
			, array( $this, 'wc_papr_settings_restriction_notices_section_callback' ), 'wc_papr_settings_page' );

		// Checkbox field for the variations restrictions
		add_settings_field( 'wc-papr-restrict-variations', esc_html__( 'Restrict variations', 'wc_papr' )
			, array( $this, 'wc_papr_settings_restrict_variations_checkbox' ), 'wc_papr_settings_page', 'wc-papr-settings-restrictions-notices-section' );

		// Checkbox field for the site-wide notice
		add_settings_field( 'wc-papr-show-site-wide-notice', esc_html__( 'Notices', 'wc_papr' )
			, array( $this, 'wc_papr_settings_show_site_wide_notice_checkbox' ), 'wc_papr_settings_page', 'wc-papr-settings-restrictions-notices-section' );

		// Checkbox field for the variations notice
		add_settings_field( 'wc-papr-show-variations-notice', ''
			, array( $this, 'wc_papr_settings_show_variations_notice_checkbox' ), 'wc_papr_settings_page', 'wc-papr-settings-restrictions-notices-section' );
	}

	public function wc_papr_settings_main_section_callback(): void {
		echo '<hr>';
		echo '<p>';
		echo esc_html__( "Select the product attributes that you would like to configure to be compatible with specific payment methods. After this selection
	, you can configure the selected attribute's terms in the Attributes page to specify which payment methods are compatible with each term.", 'wc_papr' );
		echo '</p>';
	}

	public function wc_papr_settings_select_options(): void {
		// Retrieve all product attributes
		$product_attributes = wc_get_attribute_taxonomies();

		// Get the currently saved attributes (if any)
		$selected_attributes = maybe_unserialize( $this->wc_papr_settings['wc_papr_product_attributes'] ?? array() );

		echo '<select name="wc_papr_settings[wc_papr_product_attributes][]" id="wc-papr-product-attributes" placeholder="data-placeholder='
		     . esc_attr__( 'Select product attributes', 'wc_papr' ) . '" style="width: 100%" multiple="multiple">';

		foreach ( $product_attributes as $attribute ) {
			$attribute_name = $attribute->attribute_name;
			echo '<option value="' . esc_attr( $attribute_name ) . '" ' . selected( in_array( $attribute_name, $selected_attributes ), true, false ) . '>'
			     . esc_html( $attribute->attribute_label ) . '</option>';
		}

		echo '</select>';
	}

	public function wc_papr_settings_restriction_notices_section_callback(): void {
		echo '<hr>';
		echo '<p>';
		echo esc_html__( "These settings are optional and will only work if there are attributes configured with specific payments methods.", 'wc_papr' );
		echo '</p>';
	}

	public function wc_papr_settings_restrict_variations_checkbox(): void {
		$checkbox_value = isset( $this->wc_papr_settings['wc_papr_restrict_variations'] ) ? 1 : 0;

		echo '<label><input type="checkbox" name="wc_papr_settings[wc_papr_restrict_variations]" value="1" ' . checked( 1, $checkbox_value, false ) . '>'
		     . esc_html__( 'Restrict variations options according to cart content', 'wc_papr' ) . '</label>';

		echo '<p class="description">';
		echo esc_html__( 'If enabled, the plugin will restrict the variations options displayed for a product according to the cart content. For example,
		if the cart contains a product with the color "red" and another product with the color "blue", the variations options for the color attribute will only
		contain the values "red" and "blue" when trying to add a new product to the cart.', 'wc_papr' );
		echo '</p>';
	}

	public function wc_papr_settings_show_site_wide_notice_checkbox(): void {
		$checkbox_value = isset( $this->wc_papr_settings['wc_papr_show_site_wide_notice'] ) ? 1 : 0;

		echo '<label><input type="checkbox" name="wc_papr_settings[wc_papr_show_site_wide_notice]" value="1" '
		     . checked( 1, $checkbox_value, false ) . '>' . esc_html__( 'Show a site wide notice', 'wc_papr' ) . '</label>';

		echo '<p class="description">';
		echo esc_html__( 'If enabled, the plugin will show a site wide notice if there are products in the cart that restrict the available payment methods.'
			, 'wc_papr' );
		echo '</p>';
	}

	public function wc_papr_settings_show_variations_notice_checkbox(): void {
		$checkbox_value = isset( $this->wc_papr_settings['wc_papr_show_variations_notice'] ) ? 1 : 0;

		echo '<label><input type="checkbox" name="wc_papr_settings[wc_papr_show_variations_notice]" value="1" '
		     . checked( 1, $checkbox_value, false ) . '>' . esc_html__( 'Show a notice before a product options', 'wc_papr' ) . '</label>';

		echo '<p class="description">';
		echo esc_html__( 'If enabled, the plugin will show a notice before the product options if there are products in the cart that restrict the available
		payment methods.', 'wc_papr' );
		echo '</p>';
	}

	public function wc_papr_sanitize_settings( $input ): array {
		$sanitized_input = array();

		// Sanitize select field
		if ( isset( $input['wc_papr_product_attributes'] ) ) {
			foreach ( $input['wc_papr_product_attributes'] as $attribute ) {
				if ( ! taxonomy_exists( 'pa_' . $attribute ) ) {
					wp_die( 'Invalid attribute' );
				}
			}

			$sanitized_input['wc_papr_product_attributes'] = $input['wc_papr_product_attributes'];
		}

		// Sanitize checkbox field for the variations restrictions
		if ( isset( $input['wc_papr_restrict_variations'] ) ) {
			$sanitized_input['wc_papr_restrict_variations'] = absint( $input['wc_papr_restrict_variations'] );
		}

		// Sanitize checkbox field for the site-wide notice
		if ( isset( $input['wc_papr_show_site_wide_notice'] ) ) {
			$sanitized_input['wc_papr_show_site_wide_notice'] = absint( $input['wc_papr_show_site_wide_notice'] );
		}

		// Sanitize checkbox field for the variations notice
		if ( isset( $input['wc_papr_show_variations_notice'] ) ) {
			$sanitized_input['wc_papr_show_variations_notice'] = absint( $input['wc_papr_show_variations_notice'] );
		}

		return $sanitized_input;
	}

	// Display the settings page
	public function wc_papr_settings_page(): void {
		// Check user capabilities and WooCommerce status
		if ( ! current_user_can( 'manage_options' ) || ! $this->woocommerce_active ) {
			return;
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error( 'wc_papr_settings', 'wc_papr_settings', esc_html__( 'Settings saved successfully.', 'wc_papr' ), 'updated' );
			}
			?>

			<?php settings_errors( 'wc_papr_settings' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wc_papr_settings_group' );
				do_settings_sections( 'wc_papr_settings_page' );
				submit_button( __( 'Save settings', 'wc_papr' ) );
				?>
			</form>
		</div>
		<?php
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
		$term_payment_methods = $term_payment_methods ? maybe_unserialize( $term_payment_methods ) : array();

		foreach ( $payment_methods as $method ) {
			if ( $term_payment_methods && in_array( $method->id, $term_payment_methods, true ) ) {
				$compatible_payment_methods .= '<span style="background: rgba(204,204,204,0.36); border-radius: 5px; padding: 3px; margin: 3px; display: inline-block">'
				                               . $method->title . '</span>';
			}
		}

		echo $compatible_payment_methods;
	}
}

function wc_papr_add_term_add_payment_field(): void {
	$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
	?>
	<div class="form-field">
		<label for="wc-papr-payment-methods"><?php echo esc_html__( 'Payment methods compatible with this term', 'wc_papr' ); ?></label>
		<select id="wc-papr-payment-methods" name="allowed_payment_methods[]"
		        data-placeholder="<?php echo esc_attr__( 'Select payment methods', 'wc_papr' ); ?>" style="width: 100%" multiple>
			<?php
			foreach ( $payment_methods as $method ) {
				?>
				<option value="<?php echo esc_attr( $method->id ); ?>"><?php echo esc_attr( $method->title ); ?></option>
				<?php
			}
			?>
		</select>
		<p class="description"><?php echo esc_html__( 'Select compatible payment methods for the products that use this term.', 'wc_papr' ); ?></p>
	</div>
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

function wc_papr_edit_term_add_payment_field( $term ): void {
	// We're editing a term, so we get its configured payment methods
	$term_payment_methods = get_term_meta( $term->term_id, '_wc_papr_payment_methods', true );
	$term_payment_methods = $term_payment_methods ? maybe_unserialize( $term_payment_methods ) : array();

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
				foreach ( $payment_methods as $method ) {
					?>
					<option value="<?php echo esc_attr( $method->id ); ?>"
						<?php if ( $term_payment_methods && in_array( $method->id, $term_payment_methods, true ) ) {
							echo 'selected';
						} ?>
					><?php echo esc_html( $method->title ); ?></option>
					<?php
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