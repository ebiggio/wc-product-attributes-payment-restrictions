<?php
/**
* Plugin Name: Woocommerce Product Attributes Payment Restrictions
* Description: Simple plugin to restrict payment methods based on product attributes
* Version: 1.0
* Author: Enzo Biggio (ebiggio)
* Author URI: https://github.com/ebiggio/wc-product-attributes-payment-restrictions
*/

if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin/admin-functions.php';
} else {
	require_once plugin_dir_path( __FILE__ ) . 'front/front-functions.php';
}