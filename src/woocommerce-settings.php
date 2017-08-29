<?php

if (!defined('WPINC')) {
    die;
}

return [
    'lhv-hire-purchase-support-top' => [
        'type' => 'lhv_hire_purchase_support',
    ],
    'enabled'                       => [
        'title'   => __('Enable LHV hire-purchase', 'lhv-hire-purchase'),
        'type'    => 'checkbox',
        'label'   => __('Enable LHV hire-purchase payment gateway', 'lhv-hire-purchase'),
        'default' => 'no',
    ],
    'testmode'                      => [
        'title'       => __('LHV hire-purchase test mode', 'lhv-hire-purchase'),
        'type'        => 'checkbox',
        'label'       => __('Enable LHV hire-purchase test mode', 'lhv-hire-purchase'),
        'description' => __('If this is checked, LHV hire-purchase will run in test mode. Real payments will not be made.', 'lhv-hire-purchase'),
        'default'     => 'no',
        'desc_tip'    => false,
    ],
    'testmode-admin'                => [
        'title'       => __('Website test mode', 'lhv-hire-purchase'),
        'type'        => 'checkbox',
        'label'       => __('Enable website test mode', 'lhv-hire-purchase'),
        'description' => __('If this is checked, LHV hire-purchase will only be visible to users with Administrator role. Use this if you want to test the gateway in production without customers being able to see it.', 'lhv-hire-purchase'),
        'default'     => 'no',
        'desc_tip'    => false,
    ],
    'title'                         => [
        'title'       => __('Title', 'lhv-hire-purchase') . '*',
        'type'        => 'text',
        'description' => __('This controls the payment gateway title which the user sees during checkout.', 'lhv-hire-purchase'),
        'default'     => $this->method_title,
        'desc_tip'    => false,
    ],
    'description'                   => [
        'title'       => __('Description', 'lhv-hire-purchase') . '*',
        'type'        => 'textarea',
        'description' => __('This controls the payment gateway description which the user sees during checkout.', 'lhv-hire-purchase'),
        'default'     => $this->method_description,
        'desc_tip'    => false,
    ],
    'merchant_id'                   => [
        'title'       => __('Merchant ID (VK_SND_ID)', 'lhv-hire-purchase') . '*',
        'type'        => 'text',
        'description' => sprintf(
            __('Your merchant ID should be provided by AS LHV Finance customer support. If you do not know your merchant ID, please contact LHV by email: %s or by phone: %s.', 'lhv-hire-purchase'),
            '<a href="mailto:finance@lhv.ee">finance@lhv.ee</a>',
            '<a href="tel:+3726802700">(+372) 680 2700</a>'
        ),
        'default'     => '',
        'desc_tip'    => false,
    ],
    'private_key'                   => [
        'title'       => __('Your private key', 'lhv-hire-purchase') . '*',
        'type'        => 'textarea',
        'description' => __('To sign the contract with AS LHV Finance, you generated a keypair with a public and private key. You sent the public key to AS LHV Finance. Enter the matching private key here.', 'lhv-hire-purchase'),
        'default'     => '',
        'desc_tip'    => false,
    ],
    'private_key_pass'              => [
        'title'       => __('Your private key password', 'lhv-hire-purchase'),
        'type'        => 'text',
        'description' => __('If you private key was password-protected, enter the password here. If you are not sure, just leave this blank.', 'lhv-hire-purchase'),
        'default'     => '',
        'desc_tip'    => false,
    ],
    'public_key'                    => [
        'title'       => __('LHV public key', 'lhv-hire-purchase') . '*',
        'type'        => 'textarea',
        'description' => __('AS LHV Finance public key. You probably do not need to change this.', 'lhv-hire-purchase'),
        'default'     => $defaultPublicKey,
        'desc_tip'    => false,
    ],
];
