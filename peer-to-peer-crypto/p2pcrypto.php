<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Mark Robertson
 * @package   peer-to-peer Crypto
 * @version   1.12.2
 */

/**
 * Plugin Name: peer-to-peer Crypto
 * Plugin URI: https://github.com/0x4d61726/peer-to-peer-crypto
 * Description: Peer to peer crypto transfer.
 * Version: 1.12.2
 * Author: Mark Robertson
 * Author URI: https://github.com/0x4d61726
 * Requires PHP: 5.6
 * WC requires at least: 3.4
 * WC tested up to: 9.5
 **/

if (!defined('ABSPATH')) {
    exit;
}

if (is_admin()) {
    require_once __DIR__ . '/classes/class-peerToPeer-admin-handler.php';
    new peerToPeer_Admin_Handler();
}


function peer_to_peer_crypto_get_plugin_version()
{
    $plugin_data = get_plugin_data(__FILE__);
    return $plugin_data['Version'];
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// verify WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action(
        'admin_notices',
        function () {
            $notice = <<<HTML
    <div class="notice notice-error is-dismissible">
        <p>WooCommerce peer-to-peer-crypto extension is <strong>Enabled</strong> but require 
        <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to works. Please install <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> plugin to add products and accept payments.
    </div>
HTML;
            echo $notice;

        }
    );

    return;
}

add_action(
    'plugins_loaded',
    static function () {

        // verify WooCommerce version
        if (!version_compare(WooCommerce::instance()->version, '3.4', '>=')) {
            add_action(
                'admin_notices',
                static function () {
                    $version = WooCommerce::instance()->version;
                    $notice = <<<HTML
        <div class="notice notice-error is-dismissible">
            <p>WooCommerce peer-to-peer-crypto require <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> version 3.4 or greater.
            Your current version ($version) is not compatible.</p>
        </div>
HTML;
                    echo $notice;

                }
            );

            return;
        }

        if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'config.php')) {
            require __DIR__.DIRECTORY_SEPARATOR.'config.php';
        }

        include __DIR__.DIRECTORY_SEPARATOR.'functions.php';
        include __DIR__.DIRECTORY_SEPARATOR.'jwt.php';
        include __DIR__.DIRECTORY_SEPARATOR.'parsedown.php';
        include __DIR__.DIRECTORY_SEPARATOR.'updater.php';
        include __DIR__.DIRECTORY_SEPARATOR.'wc-gateway-peer-to-peer-crypto.php';

        // updater
        $updater = new peerToPeer_Updater(__FILE__);
        add_filter('pre_set_site_transient_update_plugins', [$updater, 'setTransient']);
        add_filter('plugins_api', [$updater, 'setPluginInfo'], 10, 3);
        add_filter('upgrader_post_install', [$updater, 'postInstall'], 10, 3);

        // show "View details" link in plugin list
        add_filter(
            'plugin_row_meta',
            static function ($metas, $file, $plugin_data) {
                if ($file === plugin_basename(__FILE__)) {
                    $haveDetails = false;
                    foreach ($metas as $meta) {
                        if (strpos($meta, 'plugin-information') !== false) {
                            $haveDetails = true;
                        }
                    }

                    if (!$haveDetails) {
                        $metas[] = sprintf(
                            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                            esc_url(
                                network_admin_url(
                                    'plugin-install.php?tab=plugin-information&plugin=peer-to-peer-crypto'
                                )
                            ),
                            esc_attr(sprintf(__('More information about %s'), $plugin_data['Name'])),
                            esc_attr($plugin_data['Name']),
                            __('View details')
                        );
                    }
                }

                return $metas;
            },
            10,
            3
        );

        add_filter(
            'plugin_action_links_'.plugin_basename(__FILE__),
            static function ($links) {
                $settings = [
                    '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=peer-to-peer-crypto').'">Settings</a>',
                ];

                return array_merge($settings, $links);
            }
        );

        // register gateway
        add_filter(
            'woocommerce_payment_gateways',
            static function ($gateways) {
                $gateways[] = 'WC_Gateway_p2pcrypto';

                return $gateways;
            }
        );

        /**
         * Custom function to declare compatibility with cart_checkout_blocks feature
         */
        function peer_to_peer_crypto_declare_cart_checkout_blocks_compatibility() {
            // Check if the required class exists
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                // Declare compatibility for 'cart_checkout_blocks'
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            }
        }
        // Hook the custom function to the 'before_woocommerce_init' action
        add_action('before_woocommerce_init', 'peer_to_peer_crypto_declare_cart_checkout_blocks_compatibility');

        // Hook the custom function to the 'woocommerce_blocks_loaded' action
        add_action( 'woocommerce_blocks_loaded', 'peer_to_peer_register_order_approval_payment_method_type' );
        /**
         * Custom function to register a payment method type
         */
        function peer_to_peer_register_order_approval_payment_method_type() {
            // Check if the required class exists
            if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                return;
            }
            // Include the custom Blocks Checkout class
            require_once plugin_dir_path(__FILE__) . 'wc-gateway-class-block.php';
	    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                 function ($payment_method_registry) {
            	 $payment_method_registry->register(new WC_Gateway_p2pcrypto_Blocks);
        	}
            );
        }
    }
);