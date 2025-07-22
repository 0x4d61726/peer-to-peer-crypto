<?php
class peerToPeer_Admin_Handler
{
    public function __construct()
    {
        add_action('woocommerce_update_options_payment_gateways', [$this, 'checkAndFetchWallet']);
    }

    public function checkAndFetchWallet()
    {
        error_log("class loaded! admin handler");
        if (isset($_POST['woocommerce_peer-to-peer-crypto_settings'])) {
            $settings = $_POST['woocommerce_peer-to-peer-crypto_settings'];
    
            if (!empty($settings['merchant_id']) && !empty($settings['api_key'])) {
                $wallet_address = '0x1234567890abcdef1234567890abcdef12345678';
    
                // ✅ Save into the settings array
                $saved_settings = get_option('woocommerce_peer-to-peer-crypto_settings', []);
                $saved_settings['wallet_address'] = $wallet_address;
    
                update_option('woocommerce_peer-to-peer-crypto_settings', $saved_settings);
    
                // 🐞 Log it for proof
                error_log('✅ Wallet address saved: ' . $wallet_address);
                error_log('✅ Saved settings now: ' . print_r(get_option('woocommerce_peer-to-peer-crypto_settings'), true));
            } else {
                error_log('❌ Missing merchant_id or api_key');
            }
        } else {
            error_log('❌ No woocommerce_peer-to-peer-crypto_settings in POST');
        }
    }
    
}
