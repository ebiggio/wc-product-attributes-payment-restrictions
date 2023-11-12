<?php
/**
 * Plugin Name: WooCommerce Product Attributes Payment Restrictions
 * Plugin URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
 * Description: Simple plugin to restrict payment methods based on product attributes.
 * Version: 2.0.0
 * Author: Enzo Biggio
 * Author URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
 * Text Domain: wc-papr
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WC_PAPR_PLUGIN_VERSION = '2.0.0';

// Load the plugin text domain
load_plugin_textdomain( 'wc-papr', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/admin-functions.php';
	new WC_PAPR_Admin_Function_Handler();
} else {
	require_once plugin_dir_path( __FILE__ ) . 'front/front-functions.php';
	new WC_PAPR_Front_Function_Handler();
}