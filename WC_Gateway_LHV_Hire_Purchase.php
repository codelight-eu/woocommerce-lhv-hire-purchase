<?php

namespace Codelight\LHV;

if (!defined('WPINC')) {
    die;
}

/**
 * Class WC_Gateway_LHV_Hire_Purchase
 * @package Codelight\LHV
 *
 * WooCommerce's own coding standards conflict with PSR-2,
 * so we'll have to ignore some warnings.
 *
 * @codingStandardsIgnoreStart
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class WC_Gateway_LHV_Hire_Purchase extends \WC_Payment_Gateway
{
    /* @var string */
    public $id = 'lhv_hire_purchase';

    /* @var string */
    public $icon;

    /* @var string */
    public $method_title;

    /* @var string */
    public $method_description;

    /* @var string */
    public $title;

    /* @var string */
    public $description;

    /* @var callable */
    protected $process_payment_callback;

    /* @var boolean */
    protected $is_available;

    /**
     * Initialize fields, set up actions.
     */
    public function __construct()
    {
        $this->method_title = apply_filters(
            'lhv/hire-purchase/method_title',
            __(
                'LHV hire-purchase',
                'lhv-hire-purchase'
            )
        );

        $this->method_description = apply_filters(
            'lhv/hire-purchase/method_description',
            __(
                'Payment with LHV hire-purchase takes only a few minutes. Quick response to your application, affordable interest, down-payment from 0 euros. Premature payment free of charge.',
                'lhv-hire-purchase'
            )
        );

        $this->icon = apply_filters(
            'lhv/hire-purchase/method_icon',
            GatewayManager::getInstance()->getAssetUrl('img/lhv-hire-purchase-badge.png')
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Set the callback function for process_payment().
     *
     * @param callable $callback
     */
    public function set_process_payment_callback($callback)
    {
        $this->process_payment_callback = $callback;
    }

    /**
     * Return settings defined in WooCommerce admin.
     *
     * @return array
     */
    public function get_settings()
    {
        return [
            'enabled'                  => $this->get_option('enabled'),
            'testmode'                 => $this->get_option('testmode'),
            'testmode-admin'           => $this->get_option('testmode-admin'),
            'merchant_id'              => $this->get_option('merchant_id'),
            'private_key'              => $this->get_option('private_key'),
            'private_key_pass'         => $this->get_option('private_key_pass'),
            'public_key'               => $this->get_option('public_key'),
        ];
    }

    /**
     * Get required field keys.
     *
     * @return array
     */
    public function get_required_fields()
    {
        return [
            'enabled',
            'testmode',
            'testmode-admin',
            'merchant_id',
            'private_key',
            'public_key',
        ];
    }

    /**
     * Disable gateway
     */
    public function set_disabled()
    {
        $this->enabled = 'no';
    }

    /**
     * Check if the gateway is available in the frontend.
     *
     * @return bool
     */
    public function is_available()
    {
        $available = parent::is_available();

        if ('yes' === $this->get_option('testmode-admin') && !is_super_admin()) {
            $available = false;
        }

        return apply_filters(
            'lhv/hire-purchase/is_available',
            $available
        );
    }

    /**
     * Check if the settings are filled in correctly.
     *
     * @return array
     */
    public function validate_settings()
    {
        $settings = $this->get_settings();
        $required = $this->get_required_fields();
        $errors = [];

        foreach ($settings as $key => $value) {

            if (!in_array($key, $required)) {
                continue;
            }

            if (empty($value)) {
                $errors[] = $this->form_fields[$key]['title'];
            }
        }

        if (empty($errors)) {
            return [
                'status' => 'success',
            ];
        } else {
            return [
                'status' => 'error',
                'fields' => $errors,
            ];
        }
    }

    /**
     * Handle the payment action
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        return call_user_func(
            apply_filters('lhv/hire-purchase/process_payment_callback', $this->process_payment_callback),
            $order_id
        );
    }

    /**
     * Set up the form fields for WooCommerce admin.
     */
    public function init_form_fields()
    {
        $defaultPublicKey = $this->get_default_public_key();

        $this->form_fields = apply_filters('lhv/hire-purchase/form_fields', include('src/woocommerce-settings.php'));
    }

    /**
     * Get the default public key from assets folder.
     *
     * @return string
     */
    protected function get_default_public_key()
    {
        try {
            $key = file_get_contents(GatewayManager::getInstance()->getAssetPath('lhv.pub'));

            if (empty($key)) {
                wc_get_logger()->error('Error opening default public key file!', 'lhv');
                return '';
            }

            return $key;

        } catch (\Exception $e) {
            wc_get_logger()->error('Error opening default public key file: ' . $e->getMessage(), 'lhv');
            return '';
        }
    }


    /**
     * Generate the HTML to be displayed in WooCommerce settings page
     *
     * @return string
     */
    public function generate_lhv_hire_purchase_support_html()
    {
        $documentationUrl = GatewayManager::getInstance()->getDocumentationUrl();
        $supportUrl       = GatewayManager::getInstance()->getSupportUrl();

        ob_start();
        include('templates/admin-support.php');
        return ob_get_clean();
    }
}
