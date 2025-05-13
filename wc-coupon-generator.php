<?php
/**
 * Plugin Name: WooCommerce Virtual Coupon Generator
 * Description: Generates a one-time use coupon on purchase of a virtual coupon product.
 * Version: 1.0
 * Author: Aleksandr Mitiaiev <alexander.mityaev@gmail.com>
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'woocommerce_payment_complete', 'generate_unique_coupon_for_coupon_product' );
function generate_unique_coupon_for_coupon_product( $order_id ) {
    $order = wc_get_order( $order_id );
    $generated_codes = [];

    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        
        // Check if this is your specific virtual coupon product (e.g. by SKU)
        if ( $product && $product->get_sku() === 'virtual-coupon' ) {

            // Generate unique coupon code
            $code = 'TOYS-' . strtoupper( wp_generate_password( 6, false ) );

            // Create the coupon
            $coupon = new WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_discount_type( 'fixed_cart' ); // or 'percent' or 'fixed_product'
            $coupon->set_amount( 10 ); // 10 currency units
            $coupon->set_usage_limit( 1 );
            $coupon->set_usage_limit_per_user( 1 );
            $coupon->set_email_restrictions( [] );
            $coupon->set_individual_use( true );
            $coupon->set_description( 'Order #' . $order_id );
            $coupon->save();

            // Store it in order meta to include in emails
            $generated_codes[] = $code;
        }
    }

    if ( ! empty( $generated_codes ) ) {
        // Store in order meta
        $order->update_meta_data( '_generated_coupon_codes', $generated_codes );
        $order->save();
    }
}

/*
 * Admin functions
 */
 
add_action( 'woocommerce_admin_order_data_after_order_details', 'show_generated_coupon_in_admin_order' );
function show_generated_coupon_in_admin_order( $order ) {
    $coupon_codes = $order->get_meta( '_generated_coupon_codes' );

    if ( ! empty( $coupon_codes ) && is_array( $coupon_codes ) ) {
        echo '<p><strong>Generated Coupon Codes:</strong></p>';
        echo '<ul>';
        foreach ( $coupon_codes as $code ) {
            echo '<li><span style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px;">' . esc_html( $code ) . '</span></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . __( 'No coupon codes generated.', 'dsam' ) . '</p>';
    }
}

add_filter( 'woocommerce_order_actions', function( $actions ) {
    $actions['resend_coupon_email'] = 'Resend Coupon Email';
    return $actions;
});

add_action( 'woocommerce_order_action_resend_coupon_email', function( $order ) {
    // Trigger standard customer email with coupon info
    WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order']->trigger( $order->get_id() );
});

/*
 * Customer notifications
 */
 
add_action( 'woocommerce_email_after_order_table', 'add_coupon_to_completed_email', 20, 4 );
function add_coupon_to_completed_email( $order, $sent_to_admin, $plain_text, $email ) {
    // Only for customer completed order email
    if ( $email->id !== 'customer_completed_order' ) {
        return;
    }

    $codes = $order->get_meta( '_generated_coupon_codes' );
    if ( ! $codes ) return;
    
    if ( ! is_array( $codes ) ) {
        $codes = [ $codes ];
    }
    error_log('SMTP'.$email->id.print_r($codes,1));

    echo '<h3>Your Promo Code</h3>';
    echo '<p>Here is your promo code(s):</p>';
    echo '<ul>';
    foreach ( $codes as $code ) {
        echo '<li><strong>' . esc_html( $code ) . '</strong></li>';
    }
    echo '</ul>';
    echo '<p>You can use it for a one-time discount on your next order.</p>';
}

add_action( 'woocommerce_thankyou', 'show_generated_coupon_codes_on_thankyou', 20 );
function show_generated_coupon_codes_on_thankyou( $order_id ) {
    if ( ! $order_id ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // Retrieve coupon codes from order meta
    $coupon_codes = $order->get_meta( '_generated_coupon_codes' );

    if ( empty( $coupon_codes ) || ! is_array( $coupon_codes ) ) {
        return;
    }

    echo '<section class="woocommerce-order-coupons">';
    echo '<h2>' . __( 'Your Promo Code(s)', 'your-text-domain' ) . '</h2>';
    echo '<ul>';
    foreach ( $coupon_codes as $code ) {
        echo '<li><code>' . esc_html( $code ) . '</code></li>';
    }
    echo '</ul>';
    echo '<p>' . __( 'Each code is valid for one-time use and can be shared.', 'your-text-domain' ) . '</p>';
    echo '</section>';
}
