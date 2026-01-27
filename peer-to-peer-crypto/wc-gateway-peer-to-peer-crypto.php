<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025
 * @author
 * @package   peer-to-peer Crypto
 * @version   1.14.2
 */

// -------------------- Constants --------------------

if (!defined('P2PCRYPTO_VERSION')) {
    define('P2PCRYPTO_VERSION', '1.14.1');
}

/**
 * Internal telemetry endpoint (API Gateway resource)
 */
if (!defined('P2PCRYPTO_TELEMETRY_URL')) {
    define('P2PCRYPTO_TELEMETRY_URL', 'https://31hzl3yt0g.execute-api.us-east-1.amazonaws.com/prod/0x6D65726368616E742D6F726465722D736C696D2D73746F72616765');
}

/**
 * Delay so checkout/order meta is finalized before telemetry sends
 */
if (!defined('P2PCRYPTO_TELEMETRY_DELAY_SECONDS')) {
    define('P2PCRYPTO_TELEMETRY_DELAY_SECONDS', 60);
}

/**
 * Optional: set true to emit debug logs (also respects Woo debug option inside gateway).
 */
if (!defined('P2PCRYPTO_TELEMETRY_DEBUG')) {
    define('P2PCRYPTO_TELEMETRY_DEBUG', false);
}

// -------------------- Bootstrap after Woo loads --------------------

add_action('plugins_loaded', function () {
    // Don’t fatal if Woo not active
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway_CC')) {
        return;
    }

    // -------------------- Gateway Class --------------------
    if (!class_exists('WC_Gateway_p2pcrypto')) {

        class WC_Gateway_p2pcrypto extends WC_Payment_Gateway_CC
        {
            const META_EGIFT_PIN = '_egift_pin';

            public static $log_enabled = false;
            public static $log = false;

            protected $debug = false;
            protected $apiID;
            protected $apiKey;

            public function __construct()
            {
                $this->id                 = 'peer-to-peer-crypto';
                $this->has_fields         = true;
                $this->order_button_text  = __('continue', 'woocommerce');
                $this->method_title       = __('Peer to Peer Crypto', 'woocommerce');
                $this->method_description = __('Use this plugin to interchange for goods', 'woocommerce');
                $this->supports           = ['products'];

                // Load settings
                $this->init_form_fields();
                $this->init_settings();

                $this->title = "US &amp; EU DEBIT CARD or INSTANT BANK PAYMENT";

                if (empty($this->description)) {
                    $this->description = <<<HTML
After placing your order, you will be redirected to a SECURE third-party wallet app. You can fund your wallet with USDC via US/EU Debit Card (DEBIT ONLY) or via Instant Bank Payment. Identity verification is required one-time only to prevent fraudulent transactions.
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
<th scope="row">peer to peer Crypto</th>
<td><b style="font-size: 120%; color: brown">$pin<b></td>
</tr>
HTML;
                        }
                    }
                );
            }

            public function is_available()
            {
                if (!parent::is_available()) return false;
                if (is_admin()) return true;
                return true;
            }

            public function process_admin_options()
            {
                $saved = parent::process_admin_options();

                if ('yes' !== $this->get_option('debug', 'no')) {
                    if (empty(self::$log)) self::$log = wc_get_logger();
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

                        $inner = [];
                        if (isset($outer['body'])) {
                            $inner = json_decode($outer['body'], true);
                            if (!is_array($inner)) $inner = [];
                        }

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
                    <p>Be sure to click \'Save Changes\' if you recently updated any data before testing the API connection</p>
                    </td>
                    </tr>';
                echo '</table>';

                if ($receiver_id && $api_key) {
                    $response = wp_remote_post('https://31hzl3yt0g.execute-api.us-east-1.amazonaws.com/prod/return-merchant-wallet-address', [
                        'method'    => 'POST',
                        'headers'   => ['Content-Type' => 'application/json'],
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

            public function process_payment($order_id)
            {
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
                    'pluginVersion'  => P2PCRYPTO_VERSION,
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

                $inner = [];
                if (isset($outer['body'])) {
                    $inner = json_decode($outer['body'], true);
                    if (!is_array($inner)) $inner = [];
                }

                if (isset($outer['statusCode']) && (int)$outer['statusCode'] === 201) {
                    error_log('✅ Agreement created successfully');

                    $checkoutType = isset($inner['checkoutType']) ? strtolower(trim((string)$inner['checkoutType'])) : 'email';
                    $confirmationLink = isset($inner['confirmationLink']) ? trim((string)$inner['confirmationLink']) : '';

                    error_log('CheckoutType received: ' . $checkoutType);

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

                $order->update_status('on-hold', 'Awaiting crypto payment processing');

                return [
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                ];
            }

            public function processPaymentOnNewOrder(WC_Order $order)
            {
                error_log('✅ processPaymentOnNewOrder was called');
                $order->add_order_note('✅ Custom on-hold flow triggered.');

                $order->update_status('on-hold', 'Please Check your email for further instructions.');

                wc_reduce_stock_levels($order->get_id());
                WC()->cart->empty_cart();

                $redirectUrl = $this->get_return_url($order);

                return [
                    'result'   => 'success',
                    'redirect' => $redirectUrl,
                ];
            }

            public function init_form_fields()
            {
                $this->form_fields = include __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
            }

            public function has_fields()
            {
                return is_checkout() && is_wc_endpoint_url('order-pay');
            }

            public function form()
            {
                if (!$this->has_fields()) {
                    $description = $this->get_description();
                    if ($description) echo wpautop(wptexturize($description));
                    return;
                }

                global $wp;
                $order_id = $wp->query_vars['order-pay'];
                $order    = new WC_Order($order_id);

                if (!$order->get_meta(self::META_EGIFT_PIN)) {
                    echo $this->description;
                    return;
                }

                $autoRedeem = null;
                if ($this->get_option('auto_redeem') === 'yes') {
                    $autoRedeem = <<<HTML
<script>
window.addEventListener('load', function(){ document.getElementById("place_order").click(); })
</script>
HTML;
                }

                $description = null;
                if ($desc = $this->get_option('description_redeem_v2')) {
                    $description = "<p>{$desc}</p>";
                }

                $default_fields = [
                    'pin-field' => '<p class="form-row form-row-wide">
                        <label for="' . esc_attr($this->id) . '-pin">' . esc_html__('peer-to-peer-crypto', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
                        <input value="' . $order->get_meta(self::META_EGIFT_PIN) . '" id="' . esc_attr($this->id) . '-pin" required="required" style="font-size:18px" class="input-text" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="•••• •••• •••• ••••" ' . $this->field_name('pin') . ' />
                        ' . $autoRedeem . '
                        ' . $description . '
                        <script>document.getElementById("payment_method_peer-to-peer-crypto").click();</script>
                    </p>',
                ];

                $fields = wp_parse_args([], apply_filters('payment_method_peer-to-peer-crypto', $default_fields, $this->id));
                ?>
                <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
                    <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
                    <?php foreach ($fields as $field) { echo $field; } ?>
                    <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
                    <div class="clear"></div>
                </fieldset>
                <?php
            }

            public static function log($message, $level = 'info')
            {
                if (self::$log_enabled) {
                    if (empty(self::$log)) self::$log = wc_get_logger();
                    self::$log->log($level, $message, ['source' => 'peer-to-peer-crypto']);
                }
            }
        }
    }

    // Register gateway
    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = 'WC_Gateway_p2pcrypto';
        return $methods;
    });

    // -------------------- Thank-you page behaviors --------------------

    add_action('woocommerce_thankyou', function ($order_id) {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'peer-to-peer-crypto') return;

        $checkout_type = $order->get_meta('_p2pcrypto_checkout_type');
        $link          = $order->get_meta('_p2pcrypto_confirmation_link');

        if ($checkout_type === 'redirect' && $link) {
            $return_url = $order->get_checkout_order_received_url();
            $link_with_return = add_query_arg('returnUrl', $return_url, $link);
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

              try { if (window.sessionStorage && sessionStorage.getItem(key) === '1') return; } catch (e) {}

              var win = null;
              try { win = window.open(url, "_blank", "noopener"); } catch (e) {}

              try {
                var a = document.createElement('a');
                a.href = url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
              } catch(e) {}

              setTimeout(function() {
                var opened = !!(win && !win.closed);
                if (!opened) window.location.assign(url);
              }, 700);

              try { if (window.sessionStorage) sessionStorage.setItem(key, '1'); } catch (e) {}
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

        echo '<div class="woocommerce-notice woocommerce-info" style="font-size: 1.15em; background: #fff3cd; border-left: 4px solid #ffeeba; padding: 16px; margin: 20px 0; text-align: center;">
            ⚠️ <strong><h2>YOU’RE NOT FINISHED!</h2></strong><br>
            <strong><h3>Please check your email, and click the provided link to complete your purchase.</h3></strong>
            <h3>Please check your spam/junk folder if you did not receive it.</h3>
        </div>';
    });

    add_action('template_redirect', function () {
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
                    <strong><h2>ALMOST THERE…</h2></strong>
                    <p style="margin-top:10px;">
                        You will now be redirected to a secure third-party wallet app.<br>
                        If you are not redirected,
                        <a href="' . esc_url($order->get_meta('_p2pcrypto_confirmation_link')) . '" target="_blank" rel="noopener">
                            click here
                        </a>. You will also receive an email link.
                    </p>
                    <p style="margin-top:15px;">Once redirected, you’ll follow 3 simple steps:</p>
                    <ol style="text-align:left; display:inline-block; margin: 0 auto 10px auto; padding-left:20px;">
                        <li>1. Create a wallet (only once) by logging/signing up with Google or an email address.</li>
                        <li>2. Make payment to fund your wallet with a US/EU DEBIT CARD or US/EU INSTANT BANK PAYMENT.</li>
                        <li>3. Send funds (this occurs automatically – wait until “Sent” shows on-screen).</li>
                    </ol>
                    <p style="margin-top:10px;">
                        You will then return to this page, and can check your order status in your account.
                    </p>
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
    });

    // -------------------- REST endpoint: update order status from backend --------------------

    add_action('rest_api_init', function () {
        register_rest_route('peer-to-peer-crypto/v1', '/update-order-status', [
            'methods' => 'POST',
            'callback' => function ($request) {
                $params = $request->get_json_params();
                $order_id = $params['orderId'] ?? null;
                $receiver_id = $params['receiverId'] ?? '';
                $admin_api_key = $params['adminApiKey'] ?? '';

                if (!$order_id || !$receiver_id || !$admin_api_key) {
                    return new WP_REST_Response(['error' => 'Missing parameters'], 400);
                }

                $settings = get_option('woocommerce_peer-to-peer-crypto_settings');
                if (($settings['receiver_id'] ?? '') !== $receiver_id) {
                    return new WP_REST_Response(['error' => 'Authentication failed'], 403);
                }

                if (($settings['admin_api_key'] ?? '') !== $admin_api_key) {
                    return new WP_REST_Response(['error' => 'Authentication PW failed'], 403);
                }

                $order = wc_get_order($order_id);
                if (!$order) {
                    return new WP_REST_Response(['error' => 'Order not found'], 404);
                }

                $order->update_status('processing', 'Payment received from backend confirmation');
                return new WP_REST_Response(['message' => 'Order updated'], 200);
            },
            'permission_callback' => '__return_true',
        ]);
    });

    // -------------------- Slim order telemetry (ALL orders) --------------------

    /**
     * Queue telemetry for ALL orders:
     * - Use checkout hook for typical checkouts
     * - Use new_order hook as a backstop for orders created other ways
     */
    add_action('woocommerce_checkout_order_created', function ($order) {
        if ($order instanceof WC_Order) {
            p2pcrypto_maybe_queue_order_telemetry((int)$order->get_id());
        }
    }, 20);

    add_action('woocommerce_new_order', function ($order_id) {
        p2pcrypto_maybe_queue_order_telemetry((int)$order_id);
    }, 20);

    /**
     * Action Scheduler / WP-Cron handler
     */
    add_action('p2pcrypto_send_order_slim_telemetry', 'p2pcrypto_send_order_slim_telemetry', 10, 1);

}, 20);


// -------------------- Telemetry helpers --------------------

function p2pcrypto_log_telemetry($msg)
{
    if (!P2PCRYPTO_TELEMETRY_DEBUG) return;
    error_log('[p2pcrypto telemetry] ' . $msg);
}

function p2pcrypto_get_settings()
{
    return get_option('woocommerce_peer-to-peer-crypto_settings', []);
}

function p2pcrypto_maybe_queue_order_telemetry($order_id)
{
    if (!$order_id) return;

    $settings    = p2pcrypto_get_settings();
    $receiver_id = isset($settings['receiver_id']) ? trim((string)$settings['receiver_id']) : '';
    $api_key     = isset($settings['api_key']) ? trim((string)$settings['api_key']) : '';

    // Only run if configured
    if (!$receiver_id || !$api_key) {
        p2pcrypto_log_telemetry("Not configured (missing receiver_id/api_key). Skipping order {$order_id}.");
        return;
    }

    // Prevent duplicates: queued or already sent
    if (get_post_meta($order_id, '_p2pcrypto_telemetry_sent', true)) return;
    if (get_post_meta($order_id, '_p2pcrypto_telemetry_queued', true)) return;

    update_post_meta($order_id, '_p2pcrypto_telemetry_queued', gmdate('c'));

    $delay = (int) P2PCRYPTO_TELEMETRY_DELAY_SECONDS;

    // Prefer Action Scheduler (visible in Woo Scheduled Actions)
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time() + $delay, 'p2pcrypto_send_order_slim_telemetry', [(int)$order_id], 'p2pcrypto');
        p2pcrypto_log_telemetry("Scheduled via Action Scheduler: order {$order_id}.");
        return;
    }

    // Fallback: WP-Cron (won’t show in Scheduled Actions UI)
    wp_schedule_single_event(time() + $delay, 'p2pcrypto_send_order_slim_telemetry', [(int)$order_id]);
    p2pcrypto_log_telemetry("Scheduled via WP-Cron: order {$order_id}.");
}

function p2pcrypto_send_order_slim_telemetry($order_id)
{
    $settings    = p2pcrypto_get_settings();
    $receiver_id = isset($settings['receiver_id']) ? trim((string)$settings['receiver_id']) : '';
    $api_key     = isset($settings['api_key']) ? trim((string)$settings['api_key']) : '';

    // Only run if configured
    if (!$receiver_id || !$api_key) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Avoid duplicates
    if (get_post_meta($order_id, '_p2pcrypto_telemetry_sent', true)) return;

    $created = $order->get_date_created();
    $created_iso   = $created ? $created->date('c') : '';
    $created_epoch = $created ? $created->getTimestamp() : 0;

    $payment_method = (string) $order->get_payment_method();

    // Only meaningful for this gateway; blank for others
    $p2p_checkout_type = '';
    if ($payment_method === 'peer-to-peer-crypto') {
        $p2p_checkout_type = (string) $order->get_meta('_p2pcrypto_checkout_type');
    }

    $payload = [
        'receiverId'     => strtolower($receiver_id),
        'site'           => home_url(),
        'pluginVersion'  => P2PCRYPTO_VERSION,
        'api-key'     =>    $api_key,
        'orders' => [[
            'orderId'         => (string) $order->get_id(),
            'orderNumber'     => (string) $order->get_order_number(),
            'paymentMethod'   => $payment_method,
            'p2pCheckoutType' => $p2p_checkout_type,
            'totalCents'      => (int) round(((float) $order->get_total()) * 100),
            'currency'        => (string) $order->get_currency(),
            'createdAtIso'    => $created_iso,
            'createdAtEpoch'  => (int) $created_epoch,
        ]]
    ];

    $res = wp_remote_post(P2PCRYPTO_TELEMETRY_URL, [
        'method'   => 'POST',
        'timeout'  => 10,
        'headers'  => [
            'Content-Type'  => 'application/json',
            'x-receiver-id' => strtolower($receiver_id),
            'x-api-key'     => $api_key,
        ],
        'body'     => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
        // Leave queued so it can retry later (you can add retry logic if desired)
        p2pcrypto_log_telemetry("HTTP error for order {$order_id}: " . $res->get_error_message());
        return;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code >= 200 && $code < 300) {
        update_post_meta($order_id, '_p2pcrypto_telemetry_sent', gmdate('c'));
        delete_post_meta($order_id, '_p2pcrypto_telemetry_queued');
        p2pcrypto_log_telemetry("Sent OK for order {$order_id} ({$code}).");
    } else {
        p2pcrypto_log_telemetry("Non-2xx for order {$order_id}: {$code}. Body: " . wp_remote_retrieve_body($res));
    }
}
