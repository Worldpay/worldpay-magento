<?php

class Worldpay_Payments_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $_type;
    protected $_mode;
    protected $_methodCode;

    /**
     * [isEnabled description]
     * @param  [type]  $store_id [description]
     * @return boolean
     */
    public function isEnabled($store_id = null)
    {
        return (boolean) Mage::getStoreConfig('payment/worldpay/enabled', $store_id);
    }

    public function getMode($store_id = null)
    {
        if (!$this->_mode) {
            $this->_mode = Mage::getStoreConfig('payment/worldpay/mode', $store_id);
        }

        return $this->_mode;
    }

    public function setModel($model)
    {

        $session = Mage::getSingleton('checkout/session');
        $session->setWorldPayModel($model);

        Mage::helper('worldpay/registry')->addDataToRegistry('world_pay_model', $model);

        return $this;
    }

    public function getModel()
    {
        if (!($model = Mage::registry('world_pay_model'))) {
            return Mage::getSingleton('checkout/session')->getWorldPayModel();
        }

        return $model;
    }

    public function loadMethod($code)
    {
        return Mage::getSingleton('worldpay/utilities_paymentMethods')->loadByMethodCode($code);
    }

    public function canUseDirectModel($paymentMethodCode = null, $store_id = null)
    {
        if (Mage::getStoreConfig('payment/worldpay/direct/enabled', $store_id)) {

            if ($paymentMethodCode === null) {
                return true;
            }

            if ($method = Mage::getModel('worldpay/utilities_paymentMethods')->loadByMethodCode($paymentMethodCode)) {
                return (strtolower($method->getModel() == 'direct'));
            }
        }

        return false;
    }

    public function getNewOrderStatus($store_id)
    {
        return Mage::getStoreConfig('payment/worldpay/order_status', $store_id);
    }

    public function canUseRecurringPayments($store_id = null)
    {
        return (Mage::getSingleton('customer/session')->isLoggedIn() && (boolean) Mage::getStoreConfig('payment/worldpay_recurring/enabled', $store_id));
    }

    public function isAutoCapture($store_id = null)
    {
        return (Mage::getStoreConfig('payment/worldpay/payment_action', $store_id) == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE);
    }

    public function deleteQuote($quote)
    {
        if ($quote->getId()) {
            $quote->setActive(false)->delete();
        }
    }
    /**
     * Dumb method to explode string on '-' and return 0 indexed element.
     *
     * @param  string $string
     * @return string
     */
    public function getPartBeforeDash($string)
    {
        $parts = explode('-', $string);

        return $parts[0];
    }
    /**
     * Simple query string to array converter
     *
     * @param  string $string Query string
     * @return array
     */
    public function convertQueryStringToArray($string)
    {
        $data = array();
        parse_str($string, $data);

        return $data;
    }

}
