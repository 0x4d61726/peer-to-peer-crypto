<?php

/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Mark Robertson
 * @package   peer-to-peer Crypto
 * @version   1.13.1
 */



class WC_Gateway_p2pcrypto extends WC_Payment_Gateway_CC
{
    const META_EGIFT_PIN = '_egift_pin';

    /**
     * Whether or not logging is enabled
     *
     * @var bool
     */
    public static $log_enabled = false;

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    public static $log = false;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string
     */
    protected $apiID;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'peer-to-peer-crypto';
        $this->has_fields         = true;
        $this->order_button_text  = __('continue', 'woocommerce');
        $this->method_title       = __('Peer to Peer Crypto', 'woocommerce');
        $this->method_description = __('Use this plugin to interchange for goods', 'woocommerce');
        $this->supports           = [
            'products',
        ];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = "Debit Card or Apple Pay (U.S. ONLY)";
        // Define user set variables.
        if (empty($this->description)) {
            $this->description = <<<HTML
        You will receive an email link to a SECURE third-party wallet app after submitting your order. Options - Debit Card or Apple Pay (U.S. Debit Only) via USDC. Under $500 does NOT require ID verification.

        HTML;
        }

        $this->debug  = 'yes' === $this->get_option('debug', 'no');
        $this->apiID  = $this->get_option('api_key');
        $this->apiKey = $this->get_option('admin_api_key');

        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_filter(
            'woocommerce_order_details_after_order_table_items',
            static function (WC_Order $order) {
                $pin = $order->get_meta(self::META_EGIFT_PIN);
                if ($pin) {
                    echo <<<HTML
        <tr>
        <th scope="row">
        peer to peer Crypto
        </th>
        <td>
        <b style="font-size: 120%; color: brown">$pin<b>
        </td>
        </tr>
        HTML;
                }
            }
        );
    }

    /*Checks if order total is over $50 - Min for moonpay */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }

        if (is_admin()) {
            return true;
        }

        // $minimum = 50.00;  //Hold over func for min order. Can remove in future
        // $cart_total = WC()->cart ? floatval(WC()->cart->get_total('edit')) : 0;

        // if ($cart_total < $minimum) {
        //     return false;
        // }

        return true;
    }


    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options()
    {
        $saved = parent::process_admin_options();

        if ('yes' !== $this->get_option('debug', 'no')) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->clear('peer-to-peer-crypto');
        }

        return $saved;
    }

    public function admin_options()
    {
        parent::admin_options();

        $wallet = 'No wallet assigned yet.';
        $receiver_id = $this->get_option('receiver_id');
        $api_key = $this->get_option('api_key');

        if (isset($_POST['test_api'])) {
            $admin_api_key = $this->get_option('admin_api_key');

            $response = wp_remote_post('https://wgksndxt75.execute-api.us-east-1.amazonaws.com/prod/verify-premium-receiver-api-connection', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'receiverId' => $receiver_id,
                    'api_key' => $api_key,
                    'admin_api_key' => $admin_api_key,
                ]),
                'timeout' => 15,
            ]);

            if (!is_wp_error($response)) {
                $raw_body = wp_remote_retrieve_body($response);
                $outer = json_decode($raw_body, true);
                error_log("API Response");
                error_log(json_encode($outer, JSON_PRETTY_PRINT));
                $inner = json_decode($outer['body'], true);
                
                if (!empty($inner['message']) && $inner['message'] === 'Authentication successful') {
                    echo '<div class="notice notice-success"><p>✅ API Connection successful!</p></div>';
                } else {
                    $msg = esc_html($inner['error'] ?? 'Invalid response');
                    echo "<div class='notice notice-error'><p>❌ API Connection failed: {$msg}</p></div>";
                }
            } else {
                echo '<div class="notice notice-error"><p>❌ Error: ' . esc_html($response->get_error_message()) . '</p></div>';
            }
        }

        echo '<table class="form-table">';
        echo '<tr valign="top">
            <th scope="row">Test API Connection</th>
            <td>
            <input type="submit" name="test_api" class="button button-secondary" value="Test API Connection">
            <p>Be sure to click \'Save Changes\' if you recently updated any data before testing the API connection</>
            </td>
            </tr>';
        echo '</table>';

        if ($receiver_id && $api_key) {
            $response = wp_remote_post('https://31hzl3yt0g.execute-api.us-east-1.amazonaws.com/prod/return-merchant-wallet-address', [
                'method'    => 'POST',
                'headers'   => [
                    'Content-Type' => 'application/json',
                ],
                'body'      => json_encode([
                    'receiver_id' => $receiver_id,
                    'api_key'     => $api_key,
                ]),
                'timeout'   => 15,
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (isset($body['body'])) {
                    $inner_body = json_decode($body['body'], true);

                    if (isset($inner_body['wallet'])) {
                        $wallet = $inner_body['wallet'];
                    } else {
                        error_log('❌ Wallet address missing in inner response. Body: ' . print_r($inner_body, true));
                    }
                } else {
                    error_log('❌ Wallet address missing in outer response. Body: ' . print_r($body, true));
                }
            } else {
                error_log('❌ Failed to reach wallet API: ' . $response->get_error_message());
            }
        } else {
            error_log('❌ Missing receiver_id or api_key in settings.');
        }

        echo '<h2>Wallet Address</h2>';
        echo '<table class="form-table">';
        echo '<tr valign="top">
                <th scope="row">Assigned Wallet</th>
                <td>
                    <p style="font-family:monospace; background: #f9f9f9; padding:10px; border:1px solid #ccc;">'
            . esc_html($wallet) .
            '</p>
                    <p class="description">This wallet is assigned by web3Auth.</p>
                </td>
              </tr>';
        echo '</table>';
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        /** @var WC_Order $order */
        $order = wc_get_order($order_id);

        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $current_url .= "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $receiver_id = $this->get_option('receiver_id');

        $payload = [
            'userId'     => $order->get_billing_email(),
            'receiverId' => $receiver_id,
            'amount'     => intval(round($order->get_total() * 100)),
            'orderId'    => $order_id,
            'apiKey'     => $this->get_option('api_key'),
            'name'       => $order->get_formatted_billing_full_name(),
            'invokedUrl' => $current_url,
        ];

        $test_mode_enabled = $this->get_option('test_mode') === 'yes';
        $api_url = $test_mode_enabled
            ? 'https://fidjowcul4.execute-api.us-east-1.amazonaws.com/prod/create-new-agreement-from-merchant'
            : 'https://31hzl3yt0g.execute-api.us-east-1.amazonaws.com/prod/create-new-agreement-from-merchant';

        $response = wp_remote_post($api_url, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wc_add_notice('Could not initiate payment agreement. Please try again later.', 'error');
            return ['result' => 'failure'];
        }

        $outer = json_decode(wp_remote_retrieve_body($response), true);

        // Expecting structure { statusCode, body: '{"message":"...","agreement":{...},"checkoutType":"...","confirmationLink":"..."}' }
        $inner = [];
        if (isset($outer['body'])) {
            $inner = json_decode($outer['body'], true);
            if (!is_array($inner)) $inner = [];
        }

        if (isset($outer['statusCode']) && (int)$outer['statusCode'] === 201) {
            error_log('✅ Agreement created successfully');

            // NEW: read checkoutType and optional confirmationLink
            $checkoutType = isset($inner['checkoutType']) ? strtolower(trim((string)$inner['checkoutType'])) : 'email';
            $confirmationLink = isset($inner['confirmationLink']) ? trim((string)$inner['confirmationLink']) : '';

            error_log('CheckoutType received: ' . $confirmationLink);
            
            // If redirect, store for thank-you page JS to open in a new tab
            if ($checkoutType === 'redirect' && $confirmationLink) {
                $order->update_meta_data('_p2pcrypto_checkout_type', 'redirect');
                $order->update_meta_data('_p2pcrypto_confirmation_link', $confirmationLink);
                $order->save();
            }

        } else {
            error_log('API call failed. Full body: ' . print_r($outer, true));
            wc_add_notice('There was an issue processing your payment. Please contact support.', 'error');
            return ['result' => 'failure'];
        }

        // Keep existing behavior for order state
        $order->update_status('on-hold', 'Awaiting crypto payment processing');

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order), // go to thank-you as usual
        ];
    }


    public function processPaymentOnNewOrder(WC_Order $order)
    {

        error_log('✅ processPaymentOnNewOrder was called');
        $order->add_order_note('✅ Custom on-hold flow triggered.');

        // Mark the order as on-hold (waiting for manual payment)
        $order->update_status('on-hold', 'Please Check your email for further instructions.');

        // Reduce stock levels
        wc_reduce_stock_levels($order->get_id()); //Is this needed?

        // Remove cart contents
        WC()->cart->empty_cart();
        return [
            'result'   => 'success',
            'redirect' => $redirectUrl,
        ];
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = include __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
    }

    /**
     * @return bool
     */
    public function has_fields()   //@dev is this necessary?
    {
        return is_checkout() && is_wc_endpoint_url('order-pay');
    }

    /**
     * Frontend Form for PIN Redemption
     */
    public function form() //@dev is this necessary?
    {
        if (! $this->has_fields()) {
            $description = $this->get_description();
            if ($description) {
                echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
            }

            return;
        }

        global $wp;
        $order_id = $wp->query_vars['order-pay'];
        $order    = new WC_Order($order_id);

        if (! $order->get_meta(self::META_EGIFT_PIN)) {
            echo $this->description;

            return;
        }

        $fields = [];

        $autoRedeem = null;
        if ($this->get_option('auto_redeem') === 'yes') {
            $autoRedeem = <<<HTML
        <script>
        window.addEventListener('load', function(){
            document.getElementById("place_order").click();
        })
        </script>
        HTML;
        }

        $description = null;
        if ($description = $this->get_option('description_redeem_v2')) {
            $description = <<<HTML
        <p>
        $description
        </p>
        HTML;
        }

        $default_fields = [
            'pin-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-pin">' . esc_html__('peer-to-peer-crypto', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input value="' . $order->get_meta(self::META_EGIFT_PIN) . '" id="' . esc_attr($this->id)
                . '-pin" required="required" style="font-size:18px" class="input-text" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" '
                . $this->field_name('pin') . ' />
			    ' . $autoRedeem . '
			    ' . $description . '
			    <script>
            document.getElementById("payment_method_peer-to-peer-crypto").click();
        </script>
			</p>',
        ];

        $fields = wp_parse_args($fields, apply_filters('payment_method_peer-to-peer-crypto', $default_fields, $this->id));
?>

        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
            <?php
            foreach ($fields as $field) {
                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
            }
            ?>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
    <?php
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log($message, $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, ['source' => 'peer-to-peer-crypto']);
        }
    }
}



add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'peer-to-peer-crypto') return;

    $checkout_type = $order->get_meta('_p2pcrypto_checkout_type');
    $link          = $order->get_meta('_p2pcrypto_confirmation_link'); // raw link from API

    if ($checkout_type === 'redirect' && $link) {
        // Build the return URL (the canonical WooCommerce "order received" page for this order)
        $return_url = $order->get_checkout_order_received_url();

        // Append returnUrl param to the confirmation link.
        // NOTE: pass the *unencoded* return URL; add_query_arg() will encode it properly.
        $link_with_return = add_query_arg('returnUrl', $return_url, $link);

        // For HTML attributes
        $esc_link_attr = esc_url($link_with_return);
        ?>
        <div class="woocommerce-notice woocommerce-info" style="font-size:1.05em; background:#e7f5ff; border-left:4px solid #74c0fc; padding:16px; margin:16px 0; text-align:center;">
            <strong>Almost there…</strong> Please double check your email to confirm you sent payment.<br>
            If nothing opens, <a href="<?php echo $esc_link_attr; ?>" target="_blank" rel="noopener noreferrer">click here to continue</a>.
        </div>

        <script>
        (function() {
          var url = <?php echo json_encode($link_with_return); ?>;
          var orderId = <?php echo json_encode((string)$order_id); ?>;
          var key = 'p2pcrypto-opened-' + orderId;

          // If we've already opened once (e.g., user returned from checkout), don't open again
          try {
            if (window.sessionStorage && sessionStorage.getItem(key) === '1') {
              return;
            }
          } catch (e) { /* ignore storage errors */ }

          // Try to open a new tab/window first
          var win = null;
          try { win = window.open(url, "_blank", "noopener"); } catch (e) { /* ignore */ }

          // Create a hidden anchor as a second attempt
          try {
            var a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
          } catch(e) { /* ignore */ }

          // If still blocked, redirect this tab so the user isn't stuck
          setTimeout(function() {
            var opened = !!(win && !win.closed);
            if (!opened) {
              window.location.assign(url);
            }
          }, 700);

          // Mark as opened to avoid loops on return
          try {
            if (window.sessionStorage) {
              sessionStorage.setItem(key, '1');
            }
          } catch (e) { /* ignore */ }
        })();
        </script>

        <noscript>
            <meta http-equiv="refresh" content="0;url=<?php echo esc_attr($esc_link_attr); ?>">
            <p style="text-align:center; font-size:1.2em;">
                JavaScript is disabled. <a href="<?php echo $esc_link_attr; ?>" target="_blank" rel="noopener">Click here to continue your checkout.</a>
            </p>
        </noscript>
        <?php
        return;
    }

    // --- Email flow (unchanged) ---
    echo '<div class="woocommerce-notice woocommerce-info" style="font-size: 1.15em; background: #fff3cd; border-left: 4px solid #ffeeba; padding: 16px; margin: 20px 0; text-align: center;">
        ⚠️ <strong><h2>YOU’RE NOT FINISHED!</h2></strong><br>
        <strong><h3>Please check your email, and click the provided link to complete your purchase.</h3></strong>
        <h3>Please check your spam/junk folder if you did not receive it.</h3>
    </div>';
});


add_action('template_redirect', 'peer_to_peer_crypto_top_notice_render');

function peer_to_peer_crypto_top_notice_render()
{
    if (!is_order_received_page()) return;

    $order_id = absint(get_query_var('order-received'));
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'peer-to-peer-crypto') return;

    $checkout_type = $order->get_meta('_p2pcrypto_checkout_type');

    add_action('wp_body_open', function () use ($checkout_type, $order) {
        if ($checkout_type === 'redirect') {
            echo '<div class="woocommerce-notice woocommerce-info" 
                     style="font-size: 1.15em; background: #e7f5ff; border-left: 4px solid #74c0fc; padding: 16px; margin: 20px; text-align: center;">
                <strong><h2>ALMOST THERE…</h2></strong><br>
                <h3><strong>You will now be redirected to a secure third-party wallet app to complete your transaction.</strong></h3>
                <h3>If you are not redirected, <a href="' . esc_url($order->get_meta('_p2pcrypto_confirmation_link')) . '" target="_blank" rel="noopener">click here</a>.</h3>
                  <hr style="margin:20px 0;">
            </div>';
        } else {
            echo '<div class="woocommerce-notice woocommerce-info" 
                     style="font-size: 1.15em; background: #fff3cd; border-left: 4px solid #ffeeba; padding: 16px; margin: 20px; text-align: center;">
                ⚠️ <strong><h2>YOU’RE NOT FINISHED!</h2></strong><br>
                <strong><h3>Please check your email, and click the provided link to complete your purchase.</h3></strong>
                <h3>Please check your spam/junk folder if you did not receive it.</h3>
            </div>';
        }
    }, 0);
}

// Expose a custom REST endpoint to update order status from backend
add_action('rest_api_init', function () {
    register_rest_route('peer-to-peer-crypto/v1', '/update-order-status', [
        'methods' => 'POST',
        'callback' => 'peer_to_peer_update_order_status',
        'permission_callback' => '__return_true', // Replace with authentication as needed
    ]);
});

function peer_to_peer_update_order_status($request)
{
    $params = $request->get_json_params();
    $order_id = $params['orderId'] ?? null;
    $receiver_id = $params['receiverId'] ?? '';
    $admin_api_key = $params['adminApiKey'] ?? '';

    if (!$order_id || !$receiver_id || !$admin_api_key) {
        return new WP_REST_Response(['error' => 'Missing parameters'], 400);
    }

    $settings = get_option('woocommerce_peer-to-peer-crypto_settings');
    if (
        $settings['receiver_id'] !== $receiver_id
    ) {
        return new WP_REST_Response(['error' => 'Authentication failed'], 403);
    }

    if ($admin_api_key !== $settings['admin_api_key']) {
        error_log($admin_api_key);
        error_log($settings['admin_api_key']);
        return new WP_REST_Response(['error' => 'Authentication PW failed'], 403);
    }


    $order = wc_get_order($order_id);
    if (!$order) {
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }

    $order->update_status('processing', 'Payment received from backend confirmation');
    return new WP_REST_Response(['message' => 'Order updated'], 200);
}




function peer_to_peer_crypto_render_test_api_button()
{
    ?>
    <tr valign="top">
        <th scope="row">API Connection</th>
        <td>
            <button id="test-api-connection" class="button button-secondary">Test API Connection</button>
            <span id="api-test-result" style="margin-left:10px;"></span>
        </td>
    </tr>
    <script type="module">
        document.addEventListener("DOMContentLoaded", () => {
            const button = document.getElementById("test-api-connection");
            const result = document.getElementById("api-test-result");

            button?.addEventListener("click", async () => {
                result.textContent = "Testing...";
                try {
                    const response = await fetch(ajaxurl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: new URLSearchParams({
                            action: "test_api_connection",
                        }),
                    });

                    const data = await response.json();
                    if (data.success) {
                        result.textContent = "✅ Connected";
                    } else {
                        result.textContent = "❌ Failed: " + (data.data || "Unknown error");
                    }
                } catch (err) {
                    result.textContent = "❌ Error during request";
                }
            });
        });
    </script>
<?php
}
