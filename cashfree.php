<?php
/*
Plugin Name: Cashfree Gravity Forms
Plugin URI: https://wordpress.org/plugins/cashfree-gravity-forms
Description: Integrates Gravity Forms with Cashfree Payments, enabling end users to purchase goods and services through Gravity Forms.
Version: 1.3.0
Stable tag: 1.3.0
Author: Dev Cashfree
Author URI: https://cashfree.com
Text Domain: cashfree-gravity-forms
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Cashfree Payments is an Indian payment aggregator helping you cater to all your customers' payment needs. Cashfree Payments seamlessly integrates with Gravity Forms to let you accept payments for your Indian business on your WordPress website. With a Cashfree Payments hosted checkout and an intuitive dashboard you can be up and running in no time.

*/


define('GF_CASHFREE_VERSION', '1.3.0');

add_action('admin_post_nopriv_gf_cashfree_notify', "gf_cashfree_notify_init", 10);
add_action('gform_loaded', array('GF_Cashfree_Bootstrap', 'load'), 5);

/**
 *Load bootstrap class for cashfree
 */
class GF_Cashfree_Bootstrap
{
    /**
     *Load payment method for gravity form
     */
    public static function load()
    {
        if (method_exists('GFForms', 'include_payment_addon_framework') === false) {
            return;
        }

        require_once('class-gf-cashfree.php');

        GFAddOn::register('GF_Cashfree');

        add_filter('gform_currencies', function (array $currencies) {
            $currencies['INR'] = array(
                'name'               => __( 'India Rupee', 'gravityforms' ),
                'code'               => 'INR',
                'symbol_left'        => '₹',
                'symbol_right'       => '',
                'symbol_padding'     => ' ',
                'thousand_separator' => ',',
                'decimal_separator'  => '.',
                'decimals'           => 2
            );

            return $currencies;
        });
    }
}

/**
 * @return GF_Cashfree|null
 */
function gf_cashfree()
{
    return GF_Cashfree::get_instance();
}

/**
 * This is set to a priority of 10
 *Load notify method to trigger in case of any failure
 */
function gf_cashfree_notify_init()
{
    sleep(20);
    $gfCashfree = gf_cashfree();

    $gfCashfree->process_notify();
}
