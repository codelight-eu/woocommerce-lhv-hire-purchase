<?php

namespace Codelight\LHV\Coflink;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Request
 * @package Codelight\LHV\Coflink
 */
class Request
{
    /* Encoding used for sending data to LHV */
    const ENCODING = 'UTF-8';

    /* Order data XML root node */
    const XML_ROOT_NODE = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><CofContractProductList/>';

    /* Order data XML single item node name */
    const XML_PRODUCT_NODE = 'CofContractProduct';

    /* Order data XML valid datetime node name */
    const XML_VALID_TIME_NODE = 'ValidToDtime';

    /* Request URL */
    protected $requestUrl = 'https://www.lhv.ee/coflink';

    /* Request data fields */
    protected $fields = [
        'VK_SERVICE'  => '5011',    // Service number (5011)
        'VK_VERSION'  => '008',     // Cryptographic algorithm (008)
        'VK_SND_ID'   => '',        // Merchant ID
        'VK_REC_ID'   => 'LHV',     // Receiver ID (LHV)
        'VK_STAMP'    => '',        // Order ID
        'VK_DATA'     => '',        // Order items in XML format
        'VK_RESPONSE' => '',        // Notification URL
        'VK_RETURN'   => '',        // Redirect URL
        'VK_DATETIME' => '',        // Request date in ISO 8601 format. Ex: 2015-02.05T07:18:11+02:00
        'VK_MAC'      => false,
        'VK_EMAIL'    => '',
        'VK_PHONE'    => '',
        'VK_LANG'     => '',
    ];

    /* Request data fields that should be excluded from MAC signature generation */
    protected $signatureExcludedFields = [
        'VK_MAC',
        'VK_ENCODING',
        'VK_LANG',
    ];

    /* Items to be added in the order */
    protected $orderItems = [];

    /* Merchant private key */
    protected $privateKey;

    /* Merchant private key password */
    protected $privateKeyPass = '';

    /* Whether to run the plugin in test mode */
    protected $testMode;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->configureRequestURL();
    }

    /**
     * Get the URL for sending or request
     *
     * @return string
     */
    public function getRequestUrl()
    {
        if ('yes' === $this->testMode) {
            return $this->requestUrl . '?testRequest=true';
        }

        return $this->requestUrl;
    }

    /**
     * Add an item to the order XML
     *
     * @param string $name Product name
     * @param string $code Product SKU or product name combined with product ID
     * @param float $price Product total price including tax
     * @param float $vat Tax rate percent
     */
    public function addOrderItem($name, $code, $price, $vat)
    {
        $this->orderItems[] = [
            'Name'              => $name,
            'Code'              => $code,
            'Currency'          => 'EUR',
            'CostInclVatAmount' => $price,
            'CostVatPercent'    => $vat,
        ];
    }

    /**
     * Assemble and return the XML string with order data
     *
     * @return string
     */
    protected function getOrderDataXML()
    {
        $xml = new \SimpleXMLElement(self::XML_ROOT_NODE);

        foreach ($this->orderItems as $item) {
            $productNode = $xml->addChild(self::XML_PRODUCT_NODE);

            foreach ($item as $fieldKey => $fieldValue) {
                $productNode->addChild($fieldKey, htmlspecialchars($fieldValue, ENT_XML1, 'UTF-8'));
            }
        }

        $xml = $xml->addChild(self::XML_VALID_TIME_NODE, $this->getValidToDtime());

        $dom = dom_import_simplexml($xml);

        return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
    }

    /**
     * Assemble and return the required fields for generating a request
     *
     * @return array|bool
     */
    public function getRequestFields()
    {
        if (empty($this->orderItems)) {
            wc_get_logger()->error('Attempting to process an empty order!', ['source' => 'lhv']);
            return false;
        }

        // Add current datetime
        $this->fields['VK_DATETIME'] = $this->getDateTime();
        // Assemble XML
        $this->fields['VK_DATA']     = $this->getOrderDataXML();
        // Calculate signature
        $this->fields['VK_MAC']      = $this->getSignature();

        if (!$this->fields['VK_MAC']) {
            return false;
        }

        return $this->fields;
    }

    /**
     * Generate signature string and sign it
     *
     * @return bool|string
     */
    protected function getSignature()
    {
        $key = openssl_pkey_get_private($this->privateKey, $this->privateKeyPass);

        if (false === $key) {
            wc_get_logger()->error('Unable to open private key! Maybe a bad password?', ['source' => 'lhv']);
            return false;
        }

        $signature = false;
        if (!openssl_sign($this->getSignatureString(), $signature, $key)) {
            wc_get_logger()->error(sprintf('Unable to sign the request! %s', openssl_error_string()), ['source' => 'lhv']);
            return false;
        }

        return base64_encode($signature);
    }

    /**
     * Assemble and return a string of required data for signing
     *
     * @return string
     */
    protected function getSignatureString()
    {
        $signature = '';

        foreach ($this->fields as $key => $value) {
            if (in_array($key, $this->signatureExcludedFields)) {
                continue;
            }

            $signature .= $this->getPaddedString($value);
        }

        return $signature;
    }

    /**
     * Pad string as per LHV's specifications
     *
     * @param string $string
     * @return string
     */
    protected function getPaddedString($string = '')
    {
        return str_pad(mb_strlen($string, self::ENCODING), 3, '0', STR_PAD_LEFT) . $string;
    }

    /**
     * @return \DateTime
     */
    protected function getCurrentDateTime()
    {
        date_default_timezone_set("Europe/Tallinn");

        return new \DateTime("NOW");
    }

    /**
     * @return string
     */
    protected function getDateTime()
    {
        return $this->getCurrentDateTime()->format('c');
    }

    /**
     * @return string
     */
    protected function getValidToDtime()
    {
        return $this->getCurrentDateTime()->add(new \DateInterval('PT1H'))->format('c');
    }

    /**
     * @param bool $testMode
     */
    public function setTestMode($testMode)
    {
        $this->testMode = $testMode;
    }

    /**
     * @param string $merchantId
     */
    public function setMerchantId($merchantId)
    {
        $this->fields['VK_SND_ID'] = $merchantId;
    }

    /**
     * @param string $stamp
     */
    public function setStamp($stamp)
    {
        $this->fields['VK_STAMP'] = $stamp;
    }

    /**
     * @param string $email
     * @param string $phone
     */
    public function setCustomerData($email, $phone)
    {
        $this->fields['VK_EMAIL'] = $email;
        $this->fields['VK_PHONE'] = $phone;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->fields['VK_LANG'] = apply_filters('lhv/hire-purchase/lhv-language', $language);
    }

    /**
     * @param string $returnUrl
     */
    public function setReturnUrl($returnUrl)
    {
        $this->fields['VK_RESPONSE'] = $returnUrl;
        $this->fields['VK_RETURN']   = $returnUrl;
    }

    /**
     * @param string $key
     * @param string $pass
     */
    public function setPrivateKey($key, $pass)
    {
        $this->privateKey     = $key;
        $this->privateKeyPass = $pass;
    }

    /**
     * Allow overriding the request URL via constants in wp-config
     */
    protected function configureRequestURL()
    {
        if (defined('LHV_HIRE_PURCHASE_REQUEST_URL')) {
            $this->requestUrl = LHV_HIRE_PURCHASE_REQUEST_URL;
        }
    }
}
