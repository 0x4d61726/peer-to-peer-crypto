<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Mark Robertson
 * @package   peer-to-peer Crypto
 * @version   1.14.3
 */

defined('ABSPATH') || exit;

return [
    'enabled' => [
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable peer-to-peer crypto', 'woocommerce'),
        'default' => 'yes',
    ],
    'debug' => [
        'title' => __('Debug log', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable logging', 'woocommerce'),
        'default' => 'no',
        'description' => sprintf(__('Log events. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce'), '<code>'.WC_Log_Handler_File::get_log_file_path('peer-to-peer-crypto').'</code>'),
    ],
    'receiver_id' => [
        'title' => __('Account Email Address', 'woocommerce'),
        'type' => 'text',
        'description' => __('Enter or copy your user ID.', 'woocommerce'),
        'default' => '',
        'desc_tip' => true,
    ],
    'api_key' => [
        'title' => __('Store API Key', 'woocommerce'),
        'type' => 'password',
        'description' => __('Your API credentials.', 'woocommerce'),
        'default' => '',
        'desc_tip' => true,
    ],
    'admin_api_key' => [
        'title' => __('Admin API Key', 'woocommerce'),
        'type' => 'password',
        'description' => __('Allows for order updates after successful transactions on the blockchain.', 'woocommerce'),
        'default' => '',
        'desc_tip' => true,
    ],
    'test_mode' => [
        'title'       => __('Test Mode', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable Test Mode (Base Sepolia)', 'woocommerce'),
        'default'     => 'no',
        'description' => __('When enabled, all payments will use the Base Sepolia test network and simulate crypto transfers.', 'woocommerce'),
    ],
];