<?php

class Worldpay_Payments_Block_Payment_PaypalForm extends Mage_Payment_Block_Form
{
    protected $_methodBlocks;
    protected $_template = 'worldpay/payment/paypal.phtml';

    protected function _beforeToHtml() {

        $code = $this->getMethodCode();
        
        $this->_helper = Mage::helper('worldpay');
        $this->_code = $code;

        return parent::_beforeToHtml();
    }

    public function getBillingCountry() {
        $checkout = Mage::getSingleton('checkout/session')->getQuote();
        $billing = $checkout->getBillingAddress();
        return $billing->getCountry();
    }

    public function getClientKey() {

        $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

        if ($mode == 'Test Mode') {
            $client_key = Mage::getStoreConfig('payment/worldpay/test_client_key', Mage::app()->getStore()->getStoreId());
        }
        else {
            $client_key = Mage::getStoreConfig('payment/worldpay/live_client_key', Mage::app()->getStore()->getStoreId());
        }

        return $client_key;
    }


    public function getCountryCode() {
        return Mage::getStoreConfig('payment/worldpay/country_code', Mage::app()->getStore()->getStoreId());
    }
    public function getLanguageCode() {
        return Mage::getStoreConfig('payment/worldpay/language_code', Mage::app()->getStore()->getStoreId());
    }
}
