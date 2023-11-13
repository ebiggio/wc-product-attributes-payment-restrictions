<?php
/**
 * Plugin Name: WooCommerce Product Attributes Payment Restrictions
 * Plugin URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
 * Description: Simple plugin to restrict payment methods based on product attributes.
 * Version: 2.1.0
 * Author: Enzo Biggio
 * Author URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
 * Text Domain: wc-papr
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WC_PAPR_PLUGIN_VERSION = '2.1.0';

// Check if WooCommerce is installed and active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action( 'admin_notices', 'wc_papr_woocommerce_not_installed_notice' );

	return;
}

// Display a notice if WooCommerce is not installed and active
function wc_papr_woocommerce_not_installed_notice(): void {
	?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html__( 'WooCommerce Product Attributes Payment Restrictions requires WooCommerce to be installed and active.', 'wc-papr' ); ?></p>
    </div>
	<?php
}

// Load the plugin text domain
load_plugin_textdomain( 'wc-papr', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/admin-functions.php';
	new WC_PAPR_Admin_Function_Handler();
} else {
	require_once plugin_dir_path( __FILE__ ) . 'front/front-functions.php';
	new WC_PAPR_Front_Function_Handler();
}