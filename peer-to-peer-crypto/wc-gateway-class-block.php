<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_p2pcrypto_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'peer-to-peer-crypto';

    public function initialize() {
        $this->settings = get_option('woocommerce_peer-to-peer-crypto_settings', []);
        $this->gateway  = new WC_Gateway_p2pcrypto();
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
        $title = $this->gateway->title;

        // Decode + sanitize description so it can be safely rendered as HTML in blocks.
        $desc = isset($this->gateway->description) ? $this->gateway->description : '';
        $desc = wp_unslash($desc);
        $desc = html_entity_decode($desc, ENT_QUOTES, get_bloginfo('charset'));
        $desc = html_entity_decode($desc, ENT_QUOTES, get_bloginfo('charset'));
        $desc = wp_specialchars_decode($desc, ENT_QUOTES);
        $desc = wp_kses_post($desc);

        return [
            'title'       => $title,
            'description' => $desc,
            'supports'    => ['products'],
        ];
    }
}
?>