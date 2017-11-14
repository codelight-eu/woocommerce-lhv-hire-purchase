<?php

namespace Codelight\LHV\Coflink;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Response
 * @package Codelight\LHV\Coflink
 */
class Response
{
    const ENCODING = 'UTF-8';

    /* @var array */
    protected $data;

    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate a response from LHV
     *
     * @param $publicKey
     * @return bool|int
     */
    public function validate($publicKey)
    {
        if (isset($this->data['VK_MAC']) && !is_null($this->data['VK_MAC'])) {
            $signature = base64_decode($this->data['VK_MAC']);
            $message   =
                $this->padString($this->data['VK_SERVICE']) .
                $this->padString($this->data['VK_VERSION']) .
                $this->padString($this->data['VK_SND_ID']) .
                $this->padString($this->data['VK_REC_ID']) .
                $this->padString($this->data['VK_STAMP']) .
                $this->padString($this->data['VK_DATA']) .
                $this->padString($this->data['VK_DATETIME']);

            $verify = openssl_verify($message, $signature, openssl_get_publickey($publicKey));

            if (0 === $verify) {
                wc_get_logger()->error('Failed to verify signature!', ['source' => 'lhv']);
                $verify = false;
            } elseif (-1 === $verify) {
                wc_get_logger()->error('Error verifying signature using openssl_verify()!', ['source' => 'lhv']);
                $verify = false;
            }

            return $verify;
        }

        wc_get_logger()->info("There seems to be a technical error. Return data: \n" . wc_print_r($this->data, true), ['source' => 'lhv']);
        return false;
    }

    /**
     * Get the order ID from response
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->data['VK_STAMP'];
    }

    /**
     * Get the status from response
     *
     * @return bool|string
     */
    public function getStatus()
    {
        switch ($this->data['VK_SERVICE']) {
            case '5111':
                return 'confirmed';
            case '5112':
                //return 'manual';
                return false;
            case '5113':
                return 'rejected';
            default:
                return false;
        }
    }

    /**
     * If a customer's application goes to manual check and they click on "Back to merchant" on LHV's webpage,
     * LHV redirects the customer back to the VK_RETURN URL without any parameters whatsoever. This is an
     * attempt to detect this situation.
     * 
     * @return boolean
     */
    public function isEmpty()
    {
        return 
            count($this->data) === 1 && 
            isset($this->data['lhv-hire-purchase-payment']) &&
            $this->data['lhv-hire-purchase-payment'];
    }

    /**
     * Pad the string for validation
     *
     * @param string $string
     * @return string
     */
    protected function padString($string = '')
    {
        return str_pad(mb_strlen($string, self::ENCODING), 3, '0', STR_PAD_LEFT) . $string;
    }
}
