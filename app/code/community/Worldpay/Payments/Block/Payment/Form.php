<?php

class Worldpay_Payments_Block_Payment_Form extends Mage_Payment_Block_Form
{
    protected $_methodBlocks;
    protected $_template = 'worldpay/payment/form.phtml';
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


    public function getCardsOnFile() {

        if ($this->getPersistence()) {
            require_once(Mage::getModuleDir('', 'Worldpay_Payments')  . DS .  'lib'  . DS . 'worldpay.php');

            $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

            if ($mode == 'Test Mode') {
                $service_key = Mage::getStoreConfig('payment/worldpay/test_service_key', Mage::app()->getStore()->getStoreId());
            }
            else {
                $service_key = Mage::getStoreConfig('payment/worldpay/live_service_key', Mage::app()->getStore()->getStoreId());
            }

            $sslDisabled = Mage::getStoreConfig('payment/worldpay_cc/ssl_disabled', Mage::app()->getStore()->getStoreId());

            $worldpay = new Worldpay($service_key);

            if (Mage::app()->getStore()->isAdmin()) {
               $customerData = Mage::getSingleton('adminhtml/session_quote')->getCustomer();
            } else {
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
            }
            
            if ($mode == 'Test Mode' && $sslDisabled) {
                $worldpay->disableSSLCheck(true);
            }
            $customer_details = Mage::getModel('worldpay/payment')->getCollection()->addFieldToFilter('customer_id', $customerData->getId());
            
            $storedCards = array();

            foreach ($customer_details as $customer) {
               try {
                    $cardDetails = $worldpay->getStoredCardDetails($customer->getToken());
                }
                catch (Exception $e) {
                    return false;
                }  
                if (isset($cardDetails['maskedCardNumber'])) {
                     $storedCards[] = array(
                        'number' => $cardDetails['maskedCardNumber'],
                        'cardType' => $cardDetails['cardType'],
                        'id' => $customer->getId(),
                        'token' => $customer->getToken()
                    );
                }
                
            }
            return $storedCards;
        }
        return false;
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

    public function getMonths( ) {
        /* Returns a list of months */
        if(!self::$_months){
            self::$_months = array(''=>$this->__('Month'));
            for($i=1; $i<13; $i++) {
                self::$_months[$i] = date("$i - F", mktime(0, 0, 0, $i, 1));
            }
        }
        return self::$_months;
    }

    public function getExpiryYears( ) {
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
