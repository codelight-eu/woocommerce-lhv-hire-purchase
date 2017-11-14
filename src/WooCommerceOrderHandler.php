<?php


namespace Codelight\LHV;

if (!defined('WPINC')) {
    die;
}

use Codelight\LHV\Coflink\Request;
use Codelight\LHV\Coflink\Response;

/**
 * Class WooCommerceOrderHandler
 * @package Codelight\LHV
 */
class WooCommerceOrderHandler
{
    /* @var array */
    protected $settings;

    /**
     * @param $fields
     */
    public function setSettings($fields)
    {
        $this->settings = array_map('trim', $fields);
    }

    /**
     * Generate request to LHV
     *
     * @param $orderData
     * @param \WC_Order $order
     * @param $returnUrl
     * @return array
     */
    public function handleRequest($orderData, \WC_Order $order, $returnUrl)
    {
        $request = new Request();

        $request->setTestMode($this->settings['testmode']);
        $request->setMerchantId($this->settings['merchant_id']);
        $request->setStamp($orderData['id']);
        $request->setCustomerData($orderData['address']['email'], $orderData['address']['phone']);

        /**
         * As of 10.08.2017, the coflink environment in LHV bank is still only available Estonian,
         * despite what the spec says. Since Russian will be added 'some day', we will add support for it.
         * However, English support is not planned at the moment so we'll leave Estonian as default.
         */
        if (defined('ICL_LANGUAGE_CODE') && 'ru' === ICL_LANGUAGE_CODE) {
            $request->setLanguage('RUS');
        } else {
            $request->setLanguage('EST');
        }

        $request->setReturnUrl($returnUrl);
        $request->setPrivateKey($this->settings['private_key'], $this->settings['private_key_pass']);

        // Add all order items
        foreach ($orderData['items'] as $item) {
            $this->addItem($request, $item);
        }

        if (count($order->get_shipping_methods())) {
            foreach ($order->get_shipping_methods() as $method) {
                $this->addItem($request, $method);
            }
        }

        wc_get_logger()->info("Preparing WooCommerce order for sending to LHV. Order data: \n" . wc_print_r($orderData, true), ['source' => 'lhv']);

        $fields = $request->getRequestFields();
        wc_get_logger()->info("Order prepared for LHV. Request data: \n" . wc_print_r($fields, true), ['source' => 'lhv']);

        if (!$fields) {
            return $this->error();
        }

        return [
            'result' => 'success',
            'url'    => $request->getRequestUrl(),
            'data'   => $fields,
        ];
    }

    /**
     * Process order item data and add it to request
     *
     * @param Request $request
     * @param $item
     */
    protected function addItem(Request $request, $item)
    {
        $taxRates = \WC_Tax::get_rates($item['tax_class']);

        if (empty($taxRates)) {
            $itemRate = 0;
        } else {
            $itemRate = array_shift($taxRates)['rate'];
        }

        $price = $item->get_total() + $item->get_total_tax();
        // Do not add items with zero price, as LHV cannot handle them properly
        if ($price <= 0) {
            return;
        }

        $request->addOrderItem(
            $item->get_name(),
            $this->getItemSKU($item),
            $price,
            $itemRate
        );
    }

    /**
     * Get the SKU of the item, if possible
     *
     * @param $item
     * @return string
     */
    protected function getItemSKU($item)
    {
        if (is_a($item, 'WC_Order_Item_Product')) {
            $product = wc_get_product($item['product_id']);
            $sku     = '';

            if ($product) {
                $sku = $product->get_sku();
            }

            if (!$sku) {
                $sku = '#' . $item['product_id'] . ' ' . $item['name'];
            }

            return $sku;
        }

        if (is_a($item, 'WC_Order_Item_Shipping')) {
            return __('Shipping', 'lhv-hire-purchase') . ': ' . $item->get_name();
        }

        return $item->get_name();
    }

    /**
     * Handle response from LHV
     *
     * @param $request
     * @param $gateway
     * @return array
     */
    public function handleResponse($request, $gateway)
    {
        $response = new Response($request);
        
        // If response contains the required data to validate it, then do so
        if (!$response->isEmpty()) {
            
            if (!$response->validate($this->settings['public_key'])) {
                return $this->error();
            }

            $order = wc_get_order($response->getOrderId());

            if (!$order) {
                wc_get_logger()->error("MAC validation was successful, but order {$response->getOrderId()} does not exist!", ['source' => 'lhv']);
                return $this->error();
            }

            switch ($response->getStatus()) {
                case 'confirmed':
                    return $this->setOrderConfirmed($order, $gateway);
                case 'rejected':
                    return $this->setOrderRejectedByBank($order, $gateway);
                default:
                    wc_get_logger()->error('Unknown status (VK_SERVICE) returned by LHV!', ['source' => 'lhv']);
                    return $this->error();
            }

        } else {
            // If the response doesn't contain data, we hope it's a customer returning from LHV after his
            // application is sent to manual check. 
            // Alternatively, it's an error, but we can't really tell the difference.
            $order = wc_get_order($_GET['lhv-hire-purchase-payment']);

            if (!$order) {
                wc_get_logger()->error("VK_RETURN seems to have returned to a non-existing order ID.", ['source' => 'lhv']);
                return $this->error();
            }

            if ('pending' === $order->get_status()) {
                return $this->setOrderPending($order, $gateway); 
            }
            
            wc_get_logger()->error("There seems to be a duplicate call to VK_RETURN url. The order status is not 'pending', so no further modifications will be made.", ['source' => 'lhv']);
            return $this->error();
        }
        
    }

    /**
     * Mark payment complete and empty cart
     *
     * @param \WC_Order $order
     * @param $gateway
     * @return array
     */
    protected function setOrderConfirmed(\WC_Order $order, $gateway)
    {
        do_action('lhv/hire-purchase/order-confirmed', $order);

        $order->payment_complete();

        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => apply_filters('lhv/hire-purchase/redirect-success', $gateway->get_return_url($order), $order),
        ];
    }

    /**
     * Mark order as pending confirmation from LHV and empty cart
     *
     * @param \WC_Order $order
     * @param $gateway
     * @return array
     */
    protected function setOrderPending(\WC_Order $order, $gateway)
    {
        do_action('lhv/hire-purchase/order-pending', $order);

        $order->set_status('wc-lhv-pending');
        $order->save();
        
        WC()->cart->empty_cart();
        wc_add_notice(
            apply_filters('lhv/hire-purchase/message-pending', __('You should receive an email from AS LHV Finance soon!', 'lhv-hire-purchase'), $order));
        
        return [
            'result'   => 'success',
            'redirect' => apply_filters('lhv/hire-purchase/redirect-pending', $gateway->get_return_url($order), $order),
        ];
    }

    /**
     * Bank rejected the order - display error and redirect user back to checkout
     *
     * @param \WC_Order $order
     * @param $gateway
     * @return array
     */
    protected function setOrderRejectedByBank(\WC_Order $order, $gateway)
    {
        do_action('lhv/hire-purchase/order-rejected-bank', $order);

        wc_add_notice(
            apply_filters('lhv/hire-purchase/message-rejected-bank', __('Sorry, it appears that AS LHV Finance refused the contract. Please choose another payment method or contact support.', 'lhv-hire-purchase'), $order),
            'error'
        );

        return [
            'result'   => 'success',
            'redirect' => apply_filters('lhv/hire-purchase/redirect-rejected', wc_get_checkout_url(), $order),
        ];
    }

    /**
     * Display error message and redirect user back to checkout.
     *
     * @return array
     */
    public function error()
    {
        wc_add_notice(
            apply_filters('lhv/hire-purchase/message-technical-error', __('We were unable to process the order using LHV hire-purchase due to a technical error. Please contact the store support or use a different payment method.', 'lhv-hire-purchase')),
            'error'
        );

        return [
            'result'   => 'error',
            'redirect' => apply_filters('lhv/hire-purchase/redirect-error', wc_get_checkout_url()),
        ];
    }
}
