<?php
/**
* Plugin Name: WooCommerce Product Attributes Payment Restrictions
* Description: Simple plugin to restrict payment methods based on product attributes
* Version: 1.0.0
* Author: Enzo Biggio (ebiggio)
* Author URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
*/

const WC_PAPR_PLUGIN_VERSION = '1.0.0';

if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/admin-functions.php';
} else {
	require_once plugin_dir_path( __FILE__ ) . 'front/front-functions.php';
}