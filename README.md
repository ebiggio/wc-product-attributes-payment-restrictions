# Product Attributes Payment Restrictions for WooCommerce
Simple plugin to restrict payment methods based on product attributes. With this plugin, you can specify which payment methods are compatible with each product attribute option, and the payment methods will be filtered during checkout based on the product attributes of the products in the cart. 

## How to use
1. Select the product attributes that you want to configure to be compatible with specific payment methods in the plugin settings page
   > For example, if you have a product attribute called "Color" and you want to restrict the payment methods available for the "Red" and "Blue" options, select "Color" as the product attribute to be configured.
2. The selected product attributes will now have a new field when adding and editing its terms, where you can select the payment methods that are compatible with that option
    > Following the previous example, when editing the options (terms) of the "Color" attribute, you can now specify which payment methods are compatible with "Red" and "Blue", such as PayPal for "Red" and Stripe for "Blue".
3. During checkout, the payment methods will be filtered based on the product attributes of the products in the cart, leaving only those that are compatible with __all__ the selected product variations
    > If the customer adds a product with the "Red" option to the cart, the payment methods will be filtered to only show PayPal. If the customer then adds a product with the "Blue" option to the cart, no payment method will be available. If the customer then removes the product with the "Red" option from the cart, the payment methods will be filtered to only show Stripe.
4. There are additional options in the plugin settings page to aid and inform the customer when there are products variations in the cart that will filter the payment methods available
   - Restrict variations options according to cart content
        >    This will restrict the product variations that can be selected before adding a product to the cart if the cart already has products with payment restrictions. For example, if this option is enabled and there is a product with the "Red" option in the cart that will restrict the payment methods to show only PayPal, the customer will not be able to select the "Blue" option for any product (since it's associated with Stripe). 
   - Show a site-wide notice
        >    This will show a notice on most pages informing the customer that the payment methods available are being filtered based on the product attributes of the products in the cart
   - Show a notice before product options
        >    This will show a notice on the product page, informing the customer that the payment methods available are being filtered based on the product attributes of the products in the cart