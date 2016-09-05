<?php

class Worldpay_Payments_Block_Payment_CardForm extends Mage_Payment_Block_Form
{
    protected $_methodBlocks;
    protected $_template = 'worldpay/payment/form.phtml';
   // protected $_template = 'worldpay/payment/ownform.phtml';
    protected static $_months;
    protected static $_expiryYears;

    protected function _beforeToHtml() {

        $code = $this->getMethodCode();
        
        $this->_helper = Mage::helper('worldpay');
        $this->_code = $code;

        return parent::_beforeToHtml();
    }

    public function getBillingName() {
        $checkout = Mage::getSingleton('checkout/session')->getQuote();
        $billing = $checkout->getBillingAddress();
        return $billing->getName();
    }


    public function getPersistence() {
        return Mage::getStoreConfig('payment/worldpay_cc/card_on_file', Mage::app()->getStore()->getStoreId());
    }

    public function getThreeDSEnabled() {
        return Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());
    }
    
    public function getCardsOnFile() {
        return Mage::getModel('worldpay/paymentMethods_creditCards')->getCardsOnFile();
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

    public function getMonths() {
        /* Returns a list of months */
        if(!self::$_months){
            self::$_months = array(''=>$this->__('Month'));
            for($i=1; $i<13; $i++) {
                self::$_months[$i] = date("$i - F", mktime(0, 0, 0, $i, 1));
            }
        }
        return self::$_months;
    }

    public function getExpiryYears() {
        if(!self::$_expiryYears){
            self::$_expiryYears = array(''=>$this->__('Year'));
            $year = date('Y');
            $endYear = ($year + 14);
            while($year < $endYear){
                self::$_expiryYears[$year] = $year;
                $year++;
            }
        }
        return self::$_expiryYears;
    }
}
