<?php

defined( 'ABSPATH' ) || exit;

// Add a menu item for the plugin in the WordPress admin menu
add_action( 'admin_menu', 'wc_papr_add_admin_menu' );
function wc_papr_add_admin_menu() {
	add_menu_page(
		'Product Attributes Payment Restrictions',
		'Product Attributes Payment Restrictions',
		'manage_options',
		'wc-papr-settings',
		'wc_papr_settings_page',
		'dashicons-admin-generic'
	);
}

// Enqueue admin scripts for the settings page so that we can use Select2 (since SelectWoo is not available in this instance)
add_action( 'admin_enqueue_scripts', 'wc_papr_enqueue_admin_scripts' );
function wc_papr_enqueue_admin_scripts( $hook_suffix ) {
	// Enqueue script only on the settings page
	if ( $hook_suffix === 'toplevel_page_wc-papr-settings' ) {
		wp_enqueue_script( 'wc-papr', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'jquery' ), WC_PAPR_PLUGIN_VERSION, true );

		// Enqueue Select2 library
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), WC_PAPR_PLUGIN_VERSION, true );

		// Enqueue Select2 stylesheet
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
	}
}

// Display the settings page
function wc_papr_settings_page() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Save settings if the form is submitted
	$save_successfully = false;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		if ( isset( $_POST['wc-papr-product-attributes'] ) && is_array( $selected_attributes = $_POST['wc-papr-product-attributes'] ) ) {
			foreach ( $selected_attributes as $attribute ) {
				if ( ! taxonomy_exists( 'pa_' . $attribute ) ) {
					wp_die( 'Invalid attribute' );
				}
			}

			// Save the data in the database
			update_option( 'wc-papr-product-attributes', maybe_serialize( $selected_attributes ) );

			$save_successfully = true;
		}
	}

	// Retrieve all product attributes
	$product_attributes = wc_get_attribute_taxonomies();

	// Get the currently saved attribute (if any)
	$selected_attributes = maybe_unserialize( get_option( 'wc-papr-product-attributes' ) );

	// Display the settings form
	?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( $save_successfully ) { ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__( 'Settings saved successfully.', 'wc-papr' ); ?></p>
            </div>
		<?php } ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wc-papr-settings' ) ); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wc-papr-product-attributes"><?php echo esc_html__( 'Product attributes', 'wc-papr' ) ?></label>
                    </th>
                    <td>
                        <select name="wc-papr-product-attributes[]" id="wc-papr-product-attributes" placeholder="data-placeholder=<?php echo esc_attr__( 'Select product attributes', 'wc-papr' ); ?>" style="min-width: 400px" multiple>
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
                        <p class="description"><?php echo esc_html__( 'Select product attributes that you would like to configure to be compatible with specific payment methods.', 'wc-papr' ) ?></p>
                    </td>
                </tr>
            </table>

			<?php submit_button( 'Save settings' ); ?>
        </form>
    </div>
	<?php
}

