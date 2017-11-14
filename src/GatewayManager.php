<?php

namespace Codelight\LHV;

if (!defined('WPINC')) {
    die;
}

/**
 * Class GatewayManager
 * @package Codelight\LHV
 */
class GatewayManager
{
    /* @var string */
    protected $file;

    /* @var string */
    protected $path;

    /* @var string */
    protected $url;

    /* @var bool */
    protected $initialized = false;

    /* @var WC_Gateway_LHV_Hire_Purchase */
    protected $gateway;

    /* @var WooCommerceOrderHandler */
    protected $orderHandler;

    /* @var GatewayManager */
    protected static $instance;

    /**
     * GatewayManager constructor.
     *
     * @param string $file
     */
    protected function __construct($file)
    {
        $this->file = $file;
        $this->path = plugin_dir_path($file);
        $this->url = plugin_dir_url($file);

        do_action('lhv/hire-purchase/init');

        $this->init();
    }

    /**
     * Setup hooks.
     */
    protected function init()
    {
        // Set up response handler
        add_action('template_redirect', [$this, 'handleResponse']);

        // Register our gateway in WooCommerce
        add_filter('woocommerce_payment_gateways', [$this, 'registerGateway']);

        // Register scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

        // Output admin styles
        add_action('admin_print_scripts', [$this, 'printAdminStyles']);

        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename($this->file), [$this, 'addPluginActionLinks']);

        // Add author note
        add_filter('admin_footer_text', [$this, 'showCredits']);

        // Register custom order statuses
        add_action('init', [$this, 'registerOrderStatuses']);

        // Add custom statuses to list of WooCommerce order statuses
        add_filter('wc_order_statuses', [$this, 'addOrderStatuses']);

    }

    /**
     * Create all required dependencies.
     */
    protected function initializeDependencies()
    {
        $this->gateway = new WC_Gateway_LHV_Hire_Purchase();
        $this->orderHandler = new WooCommerceOrderHandler();

        // Get settings from gateway class
        $this->orderHandler->setSettings($this->gateway->get_settings());

        $this->initialized = true;
    }

    /**
     * Register the gateway in WooCommerce.
     * Initialize all dependencies when called for the first time.
     *
     * @param array $methods
     * @return array
     */
    public function registerGateway(array $methods)
    {
        // The filter might be called multiple times
        // but we want to initialize dependencies only once
        if (!$this->initialized) {
            $this->initializeDependencies();
        }

        // Set the callback for processing payment
        $this->gateway->set_process_payment_callback([$this, 'handleRequest']);

        // Validate settings
        $validationResult = $this->gateway->validate_settings();

        // If validation fails, mark gateway as disabled and show message
        if ('success' !== $validationResult['status']) {
            $this->gateway->set_disabled();
            $message = __('To enable LHV hire-purchase payment gateway, please go to %ssettings%s and fill in the following fields:',
                'lhv-hire-purchase');
            $settingsUrl = $this->getSettingsPageUrl();
            $fieldNames = str_replace('*', '', implode(', ', $validationResult['fields']));

            lhv_admin_notice(
                'error',
                sprintf($message . ' ', "<a href='{$settingsUrl}'>", '</a>') . $fieldNames
            );
        }

        // Register the gateway
        $methods[] = $this->gateway;

        return $methods;
    }

    /**
     * Handle sending request to LHV
     *
     * @param $orderId
     * @return array
     */
    public function handleRequest($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            wc_get_logger()->error("Invalid order ID: {$orderId}", ['source' => 'lhv']);

            return $this->orderHandler->error();
        }

        $returnUrl = apply_filters(
            'lhv/hire-purchase/return-url',
            add_query_arg(['lhv-hire-purchase-payment' => $order->get_id()], get_home_url()),
            $order
        );

        $orderData = apply_filters(
            'lhv/hire-purchase/request-order-data',
            [
                'id'      => $order->get_id(),
                'address' => $order->get_address(),
                'items'   => $order->get_items(),
            ],
            $order
        );

        return $this->orderHandler->handleRequest($orderData, $order, $returnUrl);
    }

    /**
     * Handle receiving response from LHV
     */
    public function handleResponse()
    {
        if (!isset($_REQUEST['lhv-hire-purchase-payment'])) {
            return;
        }

        wc_get_logger()->info("Received response from LHV. Data: \n" . wc_print_r($_REQUEST, true),
            ['source' => 'lhv']);

        if (!$this->initialized) {
            $this->initializeDependencies();
        }

        $result = $this->orderHandler->handleResponse($_REQUEST, $this->gateway);

        if ('success' === $result['result']) {
            if (isset($result['message'])) {
                wc_add_notice(apply_filters('lhv/hire-purchase/message-success', $result['message'], $_REQUEST));
            }

            wc_get_logger()->info("Order successful.", ['source' => 'lhv']);

            wp_safe_redirect($result['redirect']);
            exit;
        } else {
            if (isset($result['redirect'])) {
                wp_safe_redirect($result['redirect']);
                exit;
            }

            wp_safe_redirect(apply_filters('lhv/hire-purchase/redirect-error', wc_get_checkout_url()));
            exit;
        }
    }

    /**
     * Register our custom order status
     */
    public function registerOrderStatuses()
    {
        register_post_status('wc-lhv-pending', [
            'label'                     => __('Pending LHV confirmation', 'lhv-hire-purchase'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Pending LHV confirmation <span class="count">(%s)</span>',
                'Pending LHV confirmation <span class="count">(%s)</span>'),
        ]);
    }

    /**
     * Add our custom order status to the list of WooCommerce statuses
     *
     * @param $orderStatuses
     * @return mixed
     */
    public function addOrderStatuses($orderStatuses)
    {
        $orderStatuses['wc-lhv-pending'] = __('Pending LHV confirmation', 'lhv-hire-purchase');
        return $orderStatuses;
    }

    /**
     * Get the URL of a given asset.
     *
     * @param $assetPath
     * @return string
     */
    public function getAssetUrl($assetPath)
    {
        return $this->url . 'assets/' . $assetPath;
    }

    /**
     * Get the path of a given asset
     *
     * @param $assetPath
     * @return string
     */
    public function getAssetPath($assetPath)
    {
        return $this->path . 'assets/' . $assetPath;
    }

    /**
     * Enqueue front-end scripts.
     */
    public function enqueueScripts()
    {
        wp_enqueue_script('lhv-hire-purchase', $this->getAssetUrl('js/checkout.js'), ['jquery']);
    }

    /**
     * Print the styles for order status icon in admin
     */
    public function printAdminStyles()
    { ?>
        <style>
            .column-order_status mark.lhv-pending {
                content: url('<?= $this->getAssetUrl('img/order-status-pending-icon.png'); ?>');
            }

            .wp-core-ui .button-lhv-support {
                color: #fff;
                background: #37aa00;
                border-color: #37aa00 #0caa00 #0caa00;
                -webkit-box-shadow: 0 1px 0 #0caa00;
                box-shadow: 0 1px 0 #0caa00;
                text-shadow: 0 -1px 1px #0caa00, 1px 0 1px #0caa00, 0 1px 1px #0caa00, -1px 0 1px #0caa00;
            }

            .wp-core-ui .button-lhv-support:hover,
            .wp-core-ui .button-lhv-support:focus {
                background: #47bf0d;
                border-color: #37aa00 #0caa00 #0caa00;
                color: #fff;
            }
        </style>
    <?php }

    /**
     * Add settings and documentation action links
     */
    public function addPluginActionLinks($links)
    {
        $settingsPageUrl = $this->getSettingsPageUrl();
        $docsUrl = $this->getDocumentationUrl();
        $supportUrl = $this->getSupportUrl();

        return array_merge(
            ["<a href='{$settingsPageUrl}'>" . __('Settings', 'lhv-hire-purchase') . "</a>"],
            ["<a href='{$docsUrl}' target='blank'>" . __('Documentation', 'lhv-hire-purchase') . "</a>"],
            ["<a href='{$supportUrl}' target='blank'>" . __('Support', 'lhv-hire-purchase') . "</a>"],
            $links
        );
    }

    /**
     * Get the link to settings page
     *
     * @return string
     */
    public function getSettingsPageUrl()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=lhv_hire_purchase');
    }

    /**
     * Get the link to documentation page
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        if ('et_EE' === get_locale()) {
            return 'https://codelight.eu/lhv-woocommerce-jarelmaksumoodul-kasutusjuhend/';
        } else {
            return 'https://codelight.eu/lhv-hire-purchase-for-woocommerce-documentation/';
        }
    }

    /**
     * Get the link to support page
     *
     * @return string
     */
    public function getSupportUrl()
    {
        if ('et_EE' === get_locale()) {
            return 'https://codelight.eu/lhv-woocommerce-jarelmaksumooduli-klienditugi/';
        } else {
            return 'https://codelight.eu/lhv-hire-purchase-support/';
        }
    }

    /**
     * Show author credits
     *
     * @return string
     */
    public function showCredits($text)
    {
        if (
            'woocommerce_page_wc-settings' !== get_current_screen()->base ||
            !isset($_GET['section']) ||
            'lhv_hire_purchase' !== $_GET['section']
        ) {
            return $text;
        }

        return "LHV hire-purchase gateway is built and maintained by <a href='https://codelight.eu/' target='blank'>Codelight</a>";
    }

    /**
     * Get singleton instance.
     *
     * @param file
     * @return GatewayManager
     */
    public static function getInstance($file = null)
    {
        if (!isset(static::$instance)) {
            static::$instance = new static($file);
        }

        return static::$instance;
    }
}
