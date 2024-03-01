<?php

GFForms::include_payment_addon_framework();

/**
 *Load cashfree class
 */
class GF_Cashfree extends GFPaymentAddOn
{
    /**
     * Cashfree plugin config app ID and secret key
     */
    const GF_CASHFREE_APP_ID        = 'gf_cashfree_app_id';
    const GF_CASHFREE_SECRET_KEY    = 'gf_cashfree_secret_key';
    const GF_CASHFREE_ENVIRONMENT   = 'gf_cashfree_environment';

    const CF_ENVIRONMENT_PRODUCTION = "production";

    const CF_ENVIRONMENT_SANDBOX = "sandbox";
    const API_VERSION_20220901 = '2022-09-01';

    /**
     * Cashfree API attributes
     */
    const CASHFREE_ORDER_ID = 'cashfree_order_id';

    /**
     * Cookie set for one day
     */
    const COOKIE_DURATION = 86400;

    /**
     * Customer related fields
     */
    const CUSTOMER_FIELDS_NAME      = 'name';
    const CUSTOMER_FIELDS_EMAIL     = 'email';
    const CUSTOMER_FIELDS_CONTACT   = 'contact';

    /**
     * @var string Version of current plugin
     */
    protected $_version = GF_CASHFREE_VERSION;

    /**
     * @var string Minimum version of gravity forms
     */
    protected $_min_gravityforms_version = '1.9.3';

    /**
     * @var string URL-friendly identifier used for form settings, add-on settings, text domain localization...
     */
    protected $_slug = 'cashfree-gravity-forms';

    /**
     * @var string Relative path to the plugin from the plugins's folder. Example "gravityforms/gravityforms.php"
     */
    protected $_path = 'cashfree-gravity-forms/cashfree.php';

    /**
     * @var string Full path the plugin. Example: __FILE__
     */
    protected $_full_path = __FILE__;

    /**
     * @var string URL to the Gravity Forms website. Example: 'http://www.gravityforms.com' OR affiliate link.
     */
    protected $_url = 'https://www.gravityforms.com/';

    /**
     * @var string Title of the plugin to be used on the settings page, form settings and plugins page. Example: 'Gravity Forms MailChimp Add-On'
     */
    protected $_title = 'Gravity Forms Cashfree Add-On';

    /**
     * @var string Short version of the plugin title to be used on menus and other places where a less verbose string is useful. Example: 'MailChimp'
     */
    protected $_short_title = 'Cashfree';

    /**
     * Defines if the payment add-on supports callbacks.
     *
     *
     * @since  Unknown
     * @access protected
     *
     * @used-by GFPaymentAddOn::upgrade_payment()
     *
     * @var bool True if the add-on supports callbacks. Otherwise, false.
     */
    protected $_supports_callbacks = true;

    /**
     * If true, feeds will be processed asynchronously in the background.
     *
     * @since 2.2
     * @var bool
     */
    public $_async_feed_processing = false;

    // --------------------------------------------- Permissions Start -------------------------------------------------

    /**
     * @var string|array A string or an array of capabilities or roles that have access to the settings page
     */
    protected $_capabilities_settings_page = 'gravityforms_cashfree';

    /**
     * @var string|array A string or an array of capabilities or roles that have access to the form settings
     */
    protected $_capabilities_form_settings = 'gravityforms_cashfree';

    /**
     * @var string|array A string or an array of capabilities or roles that can uninstall the plugin
     */
    protected $_capabilities_uninstall = 'gravityforms_cashfree_uninstall';

    // --------------------------------------------- Permissions End ---------------------------------------------------

    /**
     * @var bool Used by Rocketgenius plugins to activate auto-upgrade.
     * @ignore
     */
    protected $_enable_rg_autoupgrade = true;

    /**
     * @var GF_Cashfree
     */
    private static $_instance = null;

    /**
     * Initiate cashfree class if not initiated
     * @return GF_Cashfree|null
     */
    public static function get_instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new GF_Cashfree();
        }

        return self::$_instance;
    }


    /**
     *Initiate gravity on frontend
     */
    public function init_frontend()
    {
        parent::init_frontend();
        add_action('gform_after_submission', array($this, 'generate_cashfree_order'), 10, 2);
    }

    /**
     * Adding configuration to add cashfree details
     * @return array[]
     */
    public function plugin_settings_fields()
    {
        return array(
            array(
                'title'     => 'Cashfree Settings',
                'fields'    => array(
                    array(
                        'name'  => self::GF_CASHFREE_APP_ID,
                        'label' => esc_html__('Cashfree App ID', $this->_slug),
                        'type'  => 'text',
                        'class' => 'medium',
                    ),
                    array(
                        'name'  => self::GF_CASHFREE_SECRET_KEY,
                        'label' => esc_html__('Cashfree Secret Key', $this->_slug),
                        'type'  => 'text',
                        'class' => 'medium',
                    ),
                    array(
                        'type'    => 'select',
                        'name'    => self::GF_CASHFREE_ENVIRONMENT,
                        'label'   => esc_html__( 'Environment', $this->_slug ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Sandbox', $this->_slug ),
                                'value' => 'sandbox'
                            ),
                            array(
                                'label' => esc_html__( 'Live', $this->_slug ),
                                'value' => 'live'
                            )
                        )
                    ),
                    array(
                        'type' => 'save',
                        'messages' => array(
                            'success' => esc_html__('Settings have been updated.', $this->_slug)
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Getting customer details
     * @param $form
     * @param $feed
     * @param $entry
     * @return array
     */
    public function get_customer_fields($form, $feed, $entry)
    {
        $fields = array();

        $billingFields = $this->billing_info_fields();

        foreach ($billingFields as $field) {
            $fieldId                = $feed['meta']['billingInformation_' . $field['name']];

            $value                  = $this->get_field_value($form, $entry, $fieldId);

            $fields[$field['name']] = $value;
        }

        return $fields;
    }

    /**
     * Handling callback response
     * @return array
     */
    public function callback()
    {
        $cashfreeOrderId    = sanitize_text_field( $_REQUEST['order_id'] );

        $entryId            = explode( '_', $cashfreeOrderId )[0];

        $entry              = GFAPI::get_entry($entryId);

        $response              = $this->get_cashfree_order($cashfreeOrderId);

        $http_code = wp_remote_retrieve_response_code( $response );

        $body = json_decode(wp_remote_retrieve_body( $response ));

        $action = array(
            'id'                => $cashfreeOrderId,
            'type'              => 'fail_payment',
            'payment_method'    => 'cashfree',
            'entry_id'          => $entry['id'],
        );

        if($http_code === 200) {
            $cfPaymentRespo = $body[0];
            if ($cfPaymentRespo->payment_status === 'SUCCESS') {
                if((number_format($cfPaymentRespo->order_amount, 2, '.', '') == number_format($entry["payment_amount"], 2, '.', ''))
                    && $cfPaymentRespo->payment_currency == $entry["currency"]) {
                    $action["type"] = 'complete_payment';
                    $action["transaction_id"] = $cfPaymentRespo->cf_payment_id;
                    $action["amount"] = $cfPaymentRespo->order_amount;
                    $action['error'] = null;
                } else {
                    $action["transaction_id"] = $cfPaymentRespo->cf_payment_id;
                    $action["amount"] =$cfPaymentRespo->order_amount;
                    $action['error'] = $cfPaymentRespo->payment_message;;
                }
            } else {
                $action["transaction_id"] = $cfPaymentRespo->cf_payment_id;
                $action["amount"] = $cfPaymentRespo->order_amount;
                $action['error'] = $cfPaymentRespo->payment_message;
            }
        } else {
            $action['error'] = $body->message;
            $action["transaction_id"] = null;
            $action["amount"] = $entry["payment_amount"];
        }
        return $action;
    }

    /**
     * Get cashfree order detail
     * @param $cashfreeOrderId
     * @return bool|object|string
     */
    public function get_cashfree_order($cashfreeOrderId)
    {
        $appId = $this->get_plugin_setting(self::GF_CASHFREE_APP_ID);

        $secretKey = $this->get_plugin_setting(self::GF_CASHFREE_SECRET_KEY);

        $environmentSetting = $this->get_plugin_setting(self::GF_CASHFREE_ENVIRONMENT);

        if($environmentSetting == 'live') {
            $url = "https://api.cashfree.com/pg/orders/".$cashfreeOrderId."/payments";
        } else {
            $url = "https://sandbox.cashfree.com/pg/orders/".$cashfreeOrderId."/payments";
        }

        $args = array(
            'headers' => array(
                'Accept'            => 'application/json',
                'x-api-version'     => self::API_VERSION_20220901,
                'x-client-id'       => $appId,
                'x-client-secret'   => $secretKey,
            )
        );
        return wp_remote_get( $url, $args );

    }

    /**
     * Verify signature returning back after payment
     * @param $post
     * @return bool
     */
    private function verify_signature($data)
    {
        $orderId        = sanitize_text_field( $data["orderId"] );
        $orderAmount    = sanitize_text_field( $data["orderAmount"] );
        $referenceId    = sanitize_text_field( $data["referenceId"] );
        $txStatus       = sanitize_text_field( $data["txStatus"] );
        $paymentMode    = sanitize_text_field( $data["paymentMode"] );
        $txMsg          = sanitize_text_field( $data["txMsg"] );
        $txTime         = sanitize_text_field( $data["txTime"] );
        $signature      = sanitize_text_field( $data["signature"] );
        $secretKey      = $this->get_plugin_setting(self::GF_CASHFREE_SECRET_KEY);
        $data           = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;
        $hashHmac       = hash_hmac('sha256', $data, $secretKey, true) ;
        $computedSignature = base64_encode($hashHmac);
        if ($signature == $computedSignature) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Handle callback after post request
     * @param $callback_action
     * @param $callback_result
     * @return false|void
     */
    public function post_callback($callback_action, $callback_result)
    {
        global $wp;

        if (is_wp_error($callback_action) || !$callback_action) {
            return false;
        }
        

        $entry = null;

        $feed = null;

        if (isset($callback_action['entry_id']) === true) {
            $entry          = GFAPI::get_entry($callback_action['entry_id']);
            $feed           = $this->get_payment_feed($entry);
            $referenceId    = rgar($callback_action, 'transaction_id');
            $amount         = rgar($callback_action, 'amount');
            $status         = rgar($callback_action, 'type');
        }

        $refId = url_to_postid(wp_get_referer());
        $refTitle = $refId > 0 ? get_the_title($refId) : "Home";

        if ($status === 'complete_payment') {
            do_action('gform_cashfree_complete_payment', $callback_action['transaction_id'], $callback_action['amount'], $entry, $feed);
        } else {
            do_action('gform_cashfree_fail_payment', $entry, $feed);
        }
        $current_url = get_permalink();

        // Remove query parameters
        $clean_permalink = remove_query_arg(array_keys($_GET), $current_url);

        ?>
        <head>
        <?php 
            echo wp_get_script_tag(
                array(
                    'src'      => plugin_dir_url(__FILE__) . 'includes/js/script.js',
                    'type' => 'text/javascript',
                )
            );
        ?>
        <link rel="stylesheet" type="text/css"
                  href="<?php echo plugin_dir_url(__FILE__) . 'includes/css/style.css'; ?>">
        </head>
        <body>
        <div class="invoice-box">
            <table cellpadding="0" cellspacing="0">
                <tr class="top">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="title"><img src="<?php echo plugin_dir_url( __FILE__ ) . 'includes/images/cflogo.svg'; ?>"
                                                       style="width:100%; max-width:300px;"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="heading">
                    <td> Payment Details</td>
                    <td> Value</td>
                </tr>
                <tr class="item">
                    <td> Status</td>
                    <td> <?php 
                        if($status == 'complete_payment'){
                            echo esc_attr("Success ✅");
                        } else {
                            echo esc_attr("Fail 🚫");
                        }; 
                    ?> </td>
                </tr>
                <?php
                if ($status == 'complete_payment') {
                    ?>
                    <tr class="item">
                        <td> Reference Id</td>
                        <td> # <?php echo esc_attr( $referenceId ); ?> </td>
                    </tr>
                    <?php
                } else {
                    ?>
                    <tr class="item">
                        <td> Transaction Error</td>
                        <td> <?php echo esc_attr( $callback_action['error'] ); ?> </td>
                    </tr>
                    <?php
                }
                ?>
                <tr class="item">
                    <td> Transaction Date</td>
                    <td> <?php echo date("F j, Y"); ?> </td>
                </tr>
                <tr class="item last">
                    <td> Amount</td>
                    <td> <?php echo esc_attr( $amount ); ?> </td>
                </tr>
            </table>
            <p style="font-size:17px;text-align:center;">Go back to the <strong><a
                        href="<?php echo esc_url( $clean_permalink ); ?>"><?php echo esc_attr($refTitle); ?></a></strong> page. </p>
            <p style="font-size:17px;text-align:center;"><strong>Note:</strong> This page will automatically redirected
                to the <strong><?php echo esc_attr( $refTitle ); ?></strong> page in <span id="cf_refresh_timer"></span> seconds.
            </p>
            <progress style="margin-left: 40%;" value="0" max="10" id="progressBar"></progress>
        </div>
        </body>
        <script type="text/javascript">setTimeout(function () {
                window.location.href = "<?php echo esc_url( $clean_permalink ); ?>"
            }, 1e3 * cfRefreshTime), setInterval(function () {
                cfActualRefreshTime > 0 ? (cfActualRefreshTime--, document.getElementById("cf_refresh_timer").innerText = cfActualRefreshTime) : clearInterval(cfActualRefreshTime)
            }, 1e3);</script>
        <?php

    }

    /**
     * @param array $entry
     * @param $form
     * @return bool|void
     */
    public function generate_cashfree_order($entry, $form)
    {
        $feed = $this->get_payment_feed($entry);
        $submissionData = $this->get_submission_data($feed, $form, $entry);

        //gravity form method to get value of payment_amount key from entry
        $paymentAmount = rgar($entry, 'payment_amount');

        //Check if gravity form is executed without any payment
        if (!$feed || empty($submissionData['payment_amount'])) {
            return true;
        }

        //It will be null first time in the entry
        if (empty($paymentAmount) === true) {
            $paymentAmount = GFCommon::get_order_total($form, $entry);
            gform_update_meta($entry['id'], 'payment_amount', $paymentAmount);
            $entry['payment_amount'] = $paymentAmount;
        }

        gform_update_meta($entry['id'], self::CASHFREE_ORDER_ID, $entry['id'].'_'.time());

        $entry[self::CASHFREE_ORDER_ID] = $entry['id'].'_'.time();

        GFAPI::update_entry($entry);

        setcookie(self::CASHFREE_ORDER_ID, $entry[self::CASHFREE_ORDER_ID],
            time() + self::COOKIE_DURATION, COOKIEPATH, COOKIE_DOMAIN, false, true);

        echo $this->generate_cashfree_form($entry, $form);

    }

    /**
     * Generate cashfree form for payment
     * @param $entry
     * @param $form
     * @return string
     */
    public function generate_cashfree_form($entry, $form)
    {
        $current_url = get_permalink();

        $feed = $this->get_payment_feed($entry, $form);

        $customerFields = $this->get_customer_fields($form, $feed, $entry);

        $paymentAmount = rgar($entry, 'payment_amount');

        $returnUrl = $current_url.'?page=gf_cashfree_callback&order_id={order_id}';

        $notifyUrl = admin_url('admin-post.php?action=gf_cashfree_notify');

        $data = array(
            "customer_details" => array(
                "customer_id" => "gravity_form_user",
                "customer_email" => !empty($customerFields[self::CUSTOMER_FIELDS_EMAIL]) ? $customerFields[self::CUSTOMER_FIELDS_EMAIL] : "user@test.com",
                "customer_phone" => !empty($customerFields[self::CUSTOMER_FIELDS_CONTACT]) ? $customerFields[self::CUSTOMER_FIELDS_CONTACT] : "9999999999",
                "customer_name" => !empty($customerFields[self::CUSTOMER_FIELDS_NAME]) ? $customerFields[self::CUSTOMER_FIELDS_NAME] : "Test User",
            ),
            "order_meta" => array(
                "return_url" => $returnUrl,
                "notify_url" => $notifyUrl
            ),
            'order_id'       => $entry[self::CASHFREE_ORDER_ID],
            'order_amount'   =>  number_format($paymentAmount, 2, '.', ''),
            'order_currency' => $entry['currency']
        );

        $environmentSetting = $this->get_plugin_setting(self::GF_CASHFREE_ENVIRONMENT);

        if($environmentSetting == 'live') {
            $curlUrl = "https://api.cashfree.com/pg/orders";
            $env = self::CF_ENVIRONMENT_PRODUCTION;
        } else {
            $curlUrl = "https://sandbox.cashfree.com/pg/orders";
            $env = self::CF_ENVIRONMENT_SANDBOX;
        }

        $response = $this->get_payments_session_id($data,$curlUrl);
        $http_code = wp_remote_retrieve_response_code( $response );
        $body     = json_decode(wp_remote_retrieve_body( $response ));
        if($http_code === 200) {
            $payment_session_id = $body->payment_session_id;

            return $this->generate_order_form($payment_session_id, $env);
        } else {
            do_action('gform_cashfree_fail_payment', $entry, $feed);
            $errorMessage = $body->message();
            echo $errorMessage;
        }
    }

    /**
     * Generate Signature
     * @param $data
     * @return array|WP_Error
     */
    public function get_payments_session_id($data, $curlUrl)
    {
        $curl_post_field = json_encode( $data );
        $appId = $this->get_plugin_setting(self::GF_CASHFREE_APP_ID);
        $secretKey = $this->get_plugin_setting(self::GF_CASHFREE_SECRET_KEY);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-version' => self::API_VERSION_20220901,
            'x-client-id' => $appId,
            'x-client-secret' => $secretKey
        ];

        $args = [
            'body'        => $curl_post_field,
            'timeout'     => 30,
            'headers'     => $headers,
        ];

        return wp_remote_post( $curlUrl, $args );
    }

    /**
     * Check is callback is valid
     * @return bool
     */
    public function is_callback_valid()
    {
        // Will check if the return url is valid
        if (rgget('page') !== 'gf_cashfree_callback') {
            return false;
        }

        return true;
    }

    /**
     * Submit payment request
     * @param $redirectUrl
     * @param $data
     */
    public function generate_order_form($payment_session_id, $env)
    {
        $html_output = <<<EOT
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cashfree Checkout Integration</title>
            <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
        </head>
        <body>
        </body>
        <script>
            const cashfree = Cashfree({
                mode: "$env"
            });
             window.addEventListener("DOMContentLoaded", function () {
                cashfree.checkout({
                    paymentSessionId: "$payment_session_id",
                    redirectTarget: "_self",
                    platformName: "gf"
                });
            });
        </script>
        </html>
        EOT;

        $allowed_html = array(
            'body'      => array(
                'onload'  => array(),
            ),
            'head'      => array(
                'onload'  => array(),
            ),
            'script' => array(
                'src' => array(
                    'https://sdk.cashfree.com/js/v3/cashfree.js'
                )
            )
        );
        return wp_kses( $html_output, $allowed_html );
    }

    /**
     * @return array[]
     */
    public function billing_info_fields()
    {
        $fields = array(
            array('name' => self::CUSTOMER_FIELDS_NAME, 'label' => esc_html__('Name', 'gravityforms'), 'required' => false),
            array('name' => self::CUSTOMER_FIELDS_EMAIL, 'label' => esc_html__('Email', 'gravityforms'), 'required' => false),
            array('name' => self::CUSTOMER_FIELDS_CONTACT, 'label' => esc_html__('Phone', 'gravityforms'), 'required' => false),
        );

        return $fields;
    }

    /**
     *Initiate notification event
     */
    public function init()
    {
        add_filter('gform_notification_events', array($this, 'notification_events'), 10, 2);

        // Supports frontend feeds.
        $this->_supports_frontend_feeds = true;

        parent::init();

    }

    /**
     * Added custom event to provide option to chose event to send notifications.
     * @param array $notification_events
     * @param array $form
     * @return array
     */
    public function notification_events($notification_events, $form)
    {
        $hasCashfreeFeed = function_exists('gf_cashfree') ? gf_cashfree()->get_feeds($form['id']) : false;

        if ($hasCashfreeFeed) {
            $paymentEvents = array(
                'complete_payment' => __('Payment Completed', 'gravityforms'),
            );

            return array_merge($notification_events, $paymentEvents);
        }

        return $notification_events;

    }

    /**
     * Add post payment action after payment success.
     * @param array $entry
     * @param array $action
     */
    public function post_payment_action($entry, $action)
    {
        $form = GFAPI::get_form($entry['form_id']);

        GFAPI::send_notifications($form, $entry, rgar($action, 'type'));
    }

    /**
     *[process_notify to process the cashfree notify]
     */
    public function process_notify()
    {
        $referenceId = sanitize_text_field( $_POST['referenceId'] );

        $signature = sanitize_text_field( $_POST['signature'] );

        $success = false;

        if ((empty($referenceId) === false) and
            (empty($signature) === false)) {
            $verifySignature = $this->verify_signature($_POST);

            if($verifySignature == false) {

                $log = array(
                    'message'   => "Signature mismatch error.",
                    'data'      => $referenceId,
                    'event'     => 'gf.cashfree.signature.verify_failed'
                );

                error_log(json_encode($log));
                status_header( 401 );
                return;
            } else {
                $success = true;
            }
        }

        if ($success === true) {
            return $this->order_complete($_POST);
        }

    }

    /**
     * Run server to server call for update event in case of network failure
     * @param $data
     */
    private function order_complete($data)
    {
        $cashfreeOrderId = sanitize_text_field( $data['orderId'] );
        $entryId = explode( '_', $cashfreeOrderId )[0];

        if (empty($entryId) === false) {
            $entry = GFAPI::get_entry($entryId);

            if (is_array($entry) === true) {

                //check the payment status not set
                if (empty($entry['payment_status']) === true) {
                    //check for valid amount
                    $paymentAmount = sanitize_text_field( $data['orderAmount'] );

                    $orderAmount = (int)round(rgar($entry, 'payment_amount') * 100);

                    //if valid amount paid mark the order complete
                    if ($paymentAmount === $orderAmount) {
                        $action = array(
                            'id'                => $cashfreeOrderId,
                            'type'              => 'complete_payment',
                            'transaction_id'    => sanitize_text_field( $data['referenceId'] ),
                            'amount'            => rgar($entry, 'payment_amount'),
                            'entry_id'          => $entryId,
                            'payment_method'    => 'cashfree',
                            'error'             => null,
                        );

                        $this->complete_payment($entry, $action);
                    }
                }
            }
        }
    }
}