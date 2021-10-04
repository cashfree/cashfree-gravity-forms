## Official Cashfree Payment Gateway plugin for Gravity Forms.

### Description

This is the official Cashfree payment gateway plugin for Gravity Forms. Allows you to accept credit cards, debit cards, net banking and wallets with the gravity forms plugin.

This is compatible with version greater than 1.9.3 gravity forms.

### Dependencies

1. Wordpress v3.9.2 and later
2. Gravity Forms v1.9.3 and later
3. PHP v5.6.0 and later
4. php-curl

### Installation

1. Install the plugin from the [Wordpress Plugin Directory](https://wordpress.org/plugins/cashfree-gravity-forms).
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.
3. There are 2 action hooks available corresposding to payment failed and payment success. By using these hooks, corresponding action can be implemented.

	a) gform_cashfree_fail_payment with 2 params ($entry, $feed)
	b) gform_cashfree_complete_payment with 4 params ($payment_transaction_id,$amount, $entry, $feed)

   Above-mentioned hooks can be used to handle the success and failure cases of the payment.

### Steps to integrate Cashfree with your Gravity Form

1. Visit the Gravity Forms settings page, and click on the Cashfree tab.
2. Add in your App ID and Secret Key.
3. Add a form using gravity form and add fields to the form and save it.
4. Click on setting of that form and click on Cashfree.
5. Add a Cashfree feed to support Cashfree payment to the particular form.

### Support

Visit [cashfree.com](https://cashfree.com) for support requests or email us at <techsupport@cashfree.com>.

### License

The Cashfree Gravity Forms plugin is released under the GPLv2 license, same as that
of WordPress. See the LICENSE file for the complete LICENSE text.
