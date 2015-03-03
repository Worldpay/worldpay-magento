<?php

abstract class Worldpay_Payments_Model_PaymentMethods_Abstract extends Mage_Payment_Model_Method_Abstract
{

    protected $_isGateway               = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = false;
    protected $_formBlockType           = 'worldpay/payment_form';
    protected $_response;

    protected static $_postData;
    protected static $_type;
    protected static $_model;

    public function assignData($data)
    {
        parent::assignData($data);
        $session = Mage::getSingleton('core/session');
        $session->setData('payment_token', $data->token);
        $session->setData('saved_card', false);

        $persitent = Mage::getStoreConfig('payment/worldpay_cc/card_on_file', Mage::app()->getStore()->getStoreId());
        // If token is persistent save in db
        if($persitent && Mage::getSingleton('customer/session')->isLoggedIn()) {

            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            
            if ($data->token) {
                $token_exists = Mage::getModel('worldpay/payment')->getCollection()->
                addFieldToFilter('customer_id', $customerData->getId())->
                addFieldToFilter('token', $data->token)->
                getFirstItem();

                if (empty($token_exists['token'])) {
                    $data = array(
                        'token' => $data->token,
                        'customer_id' => $customerData->getId()
                    );
                    $collection = Mage::getModel('worldpay/payment')->setData($data)->save();
                }
            }
            else if ($data->savedcard) {
                // Customer has chosen a saved card
                $session->setData('payment_token', $data->savedcard);
                $session->setData('saved_card', true);
            }
        }
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {       

        $store_id = Mage::app()->getStore()->getStoreId();

        $orderId = $payment->getOrder()->getIncrementId();

        if (!($order = $payment->getOrder())) {
            return false;
        }
        
        require_once(Mage::getModuleDir('', 'Worldpay_Payments')  . DS .  'lib'  . DS . 'worldpay.php');

        $session = Mage::getSingleton('core/session');
        $token = $session->getData('payment_token');
        $savedCard = $session->getData('saved_card');

        $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

        $sslDisabled = Mage::getStoreConfig('payment/worldpay_cc/ssl_disabled', Mage::app()->getStore()->getStoreId());

        if ($mode == 'Test Mode') {
            $service_key = Mage::getStoreConfig('payment/worldpay/test_service_key', Mage::app()->getStore()->getStoreId());
        }
        else {
            $service_key = Mage::getStoreConfig('payment/worldpay/live_service_key', Mage::app()->getStore()->getStoreId());
        }

        $worldpay = new Worldpay($service_key);

        if ($mode == 'Test Mode' && $sslDisabled) {
            $worldpay->disableSSLCheck(true);
        }
        
       
        $checkout = Mage::getSingleton('checkout/session')->getQuote();
        $billing = $checkout->getBillingAddress();

        $order_description = Mage::getStoreConfig('payment/'.$this->_code.'/description', $store_id);

        if (!$order_description) {
            $order_description = "Order";
        }
        

        $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
        $name = $billing->getName();
        $billing_address = array(
            "address1"=>$billing->getStreet(1),
            "address2"=>$billing->getStreet(2),
            "address3"=>$billing->getStreet(3),
            "postalCode"=>$billing->getPostcode(),
            "city"=>$billing->getCity(),
            "state"=>"",
            "countryCode"=>$billing->getCountry(),
        );
      

        try {

            $response = $worldpay->createOrder(array(
                'token' => $token,
                'orderDescription' => $order_description,
                'amount' => $amount*100,
                'currencyCode' => $currency_code,
                'name' => $name,
                'billingAddress' => $billing_address,
                'customerOrderCode' => $orderId
            ));
            
            if ($response['paymentStatus'] === 'SUCCESS') {
                $this->setStore($payment->getOrder()->getStoreId());
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setAmount($amount);
                
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
               

                $formatedPrice = $order->getBaseCurrency()->formatTxt($amount);

                // Delay order processing
                // $payment->setIsTransactionClosed(0);
                // $payment->setIsTransactionPending(true);
                // $payment->setSkipOrderProcessing(true);


                ///// Set order to success

                $payment->setShouldCloseParentTransaction(1)
                ->setIsTransactionClosed(1)
                ->registerCaptureNotification($amount);
               
            }
            else {
                throw new Exception(print_r($response, true));
            }

           
        }
        catch (Exception $e) {

            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $this->setStore($payment->getOrder()->getStoreId());
            Mage::throwException('Payment failed, please try again later ' . $e->getMessage());
        }

        return $this;
    }

    public function getConfigPaymentAction()
    {
        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
    }

    public function isAvailable($quote=null)
    {

        $store_id      = is_object($quote) ? $quote->getStoreId() : Mage::app()->getStore()->getStoreId();
        $methodEnabled = (boolean) Mage::getStoreConfig('payment/'.$this->_code.'/enabled', $store_id);

        return $methodEnabled;
    }

    public function getPaymentMethodsType() {
        return $this->_code;
    }

    public function getMethodCode() {
        return $this->_code;
    }
    
    public function refund(Varien_Object $payment, $amount)
    {
        if ($order = $payment->getOrder()) {

            require_once(Mage::getModuleDir('', 'Worldpay_Payments')  . DS .  'lib'  . DS . 'worldpay.php');

            $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

            $sslDisabled = Mage::getStoreConfig('payment/worldpay_cc/ssl_disabled', Mage::app()->getStore()->getStoreId());

            if ($mode == 'Test Mode') {
                $service_key = Mage::getStoreConfig('payment/worldpay/test_service_key', Mage::app()->getStore()->getStoreId());
            }
            else {
                $service_key = Mage::getStoreConfig('payment/worldpay/live_service_key', Mage::app()->getStore()->getStoreId());
            }

            $worldpay = new Worldpay($service_key);

            if ($mode == 'Test Mode' && $sslDisabled) {
                $worldpay->disableSSLCheck(true);
            }

            $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

            // $amount = round($amount, 2, PHP_ROUND_HALF_EVEN) * 100;
            try {
                $worldpay->refundOrder($payment->getData('last_trans_id'));
                return $this;
            }
            catch (Exception $e) {
                 Mage::throwException('Refund failed ' . $e->getMessage());
            }
        }

        Mage::throwException('No matching order found in Worldpay to refund. Please visit your WorldPay merchant interface and refund the order manually.');
    }

    public function void(Varien_Object $payment)
    {
        return true;
    }
}
