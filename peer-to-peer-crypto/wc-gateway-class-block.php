<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_p2pcrypto_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'peer-to-peer-crypto';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_peer-to-peer-crypto_settings', [] );
        $this->gateway = new WC_Gateway_p2pcrypto();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

    wp_register_script(
        'peer-to-peer-crypto-blocks-integration',
        plugin_dir_url(__FILE__) . 'checkout.js',
        [
            'wp-element',
            'wp-i18n',
            'wc-settings',
            'wc-blocks-registry',
            'wp-html-entities',
        ],
        time(),
        true
    );


        return [ 'peer-to-peer-crypto-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description, // ✅ Add this line
            'supports' => ['products'], // or pass features if needed
        ];
    }

}
?>