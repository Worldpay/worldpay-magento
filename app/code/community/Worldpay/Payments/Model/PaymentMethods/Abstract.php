<?php

abstract class Worldpay_Payments_Model_PaymentMethods_Abstract extends Mage_Payment_Model_Method_Abstract
{

    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = false;
    protected $_canManageRecurringProfiles = false;
    protected $_formBlockType           = 'worldpay/payment_form';
    protected $_response;

    protected static $_postData;
    protected static $_type;
    protected static $_model;

    public function assignData($data)
    {
        $logger = Mage::helper('worldpay/logger');
        parent::assignData($data);
        $session = Mage::getSingleton('core/session');
        $session->setData('payment_token', $data->token);
        $session->setData('saved_card', false);
        $persistent = Mage::getStoreConfig('payment/worldpay_cc/card_on_file', Mage::app()->getStore()->getStoreId());
        
        // If token is persistent save in db
        if($persistent&& (Mage::getSingleton('customer/session')->isLoggedIn() || Mage::app()->getStore()->isAdmin())) {

            if (Mage::app()->getStore()->isAdmin()) {
                $customerData = Mage::getSingleton('adminhtml/session_quote')->getCustomer();
            } else {
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
            }
            
            if ($data->token) {
                if ($data->savecard) {
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
            }
            else if ($data->savedcard) {
                // Customer has chosen a saved card
                $session->setData('payment_token', $data->savedcard);
                $session->setData('saved_card', true);
            }
        }
        return $this;
    }

    private function setupWorldpay() {
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

        return $worldpay;
    }


    public function createOrder(Varien_Object $payment, $amount, $authorize) {
        $store_id = Mage::app()->getStore()->getStoreId();

        if ($payment->getOrder()) {
            $orderId = $payment->getOrder()->getIncrementId();
        } else  {
            $orderId = $payment->getQuote()->getIncrementId();
           // $order = $payment->getOrder())
        }
        
        $logger = Mage::helper('worldpay/logger');

        $session = Mage::getSingleton('core/session');
        $token = $session->getData('payment_token');
        $savedCard = $session->getData('saved_card');

        $logger->log('Begin create order');

        $session->setData('wp_3dsSuccess', false);
        $session->setData('wp_orderCode', false);

        $worldpay = $this->setupWorldpay();

        if (Mage::app()->getStore()->isAdmin()) {
            $checkout = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        } else {
            $checkout = Mage::getSingleton('checkout/session')->getQuote();
        }

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

            $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

            $orderType = 'ECOM';
            $threeDS = Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());
            if (Mage::app()->getStore()->isAdmin()) {
                $orderType = 'MOTO';
                $threeDS = false;
            }

            if ($threeDS && $mode == 'Test Mode') {
                $name = '3D';
            }
            
            $settlementCurrency = Mage::getStoreConfig('payment/worldpay_cc/settlementcurrency', Mage::app()->getStore()->getStoreId());


            $createOrderRequest = array(
                'token' => $token,
                'orderDescription' => $order_description,
                'amount' => $amount*100,
                'currencyCode' => $currency_code,
                'name' => $name,
                'orderType' => $orderType,
                'is3DSOrder' => $threeDS,
                'authoriseOnly' => $authorize,
                'billingAddress' => $billing_address,
                'customerOrderCode' => $orderId,
                'settlementCurrency' => $settlementCurrency
            );

            $logger->log('Order Request: ' .  print_r($createOrderRequest, true));
            $response = $worldpay->createOrder($createOrderRequest);
            $logger->log('Order Response: ' .  print_r($response, true));
            
            if ($response['paymentStatus'] === 'SUCCESS') {
                $this->setStore($payment->getOrder()->getStoreId());
                $logger->log('Order: ' .  $response['orderCode'] . ' SUCCESS');
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setAmount($amount);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
               // $formatedPrice = $order->getBaseCurrency()->formatTxt($amount);
                $payment->setShouldCloseParentTransaction(1)
                ->setIsTransactionClosed(1)
                ->registerCaptureNotification($amount);
            }
            else if ($response['paymentStatus'] == 'AUTHORIZED') {
                $this->setStore($payment->getOrder()->getStoreId());
                $logger->log('Order: ' .  $response['orderCode'] . ' AUTHORIZED');
                $payment->setIsTransactionClosed(0);
                $payment->setSkipOrderProcessing(true);
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setAmount($amount);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
            }
            else if ($response['is3DSOrder']) {
                $session = Mage::getSingleton('core/session');
                $logger->log('Starting 3DS Order: ' .  $response['orderCode']);
                $session->setData('wp_3dsSuccess', false);
                $session->setData('wp_redirectURL', $response['redirectURL']);
                $session->setData('wp_oneTime3DsToken', $response['oneTime3DsToken']);
                $session->setData('wp_orderCode', $response['orderCode']);

                // IF normal checkout
                $currentUrl = Mage::helper('core/url')->getCurrentUrl();
                $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
                $path = $url->getPath();
                if (strpos($path, 'onepage') === false) {
                    Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('worldpay/threeDS'));
                    Mage::app()->getResponse()->sendResponse();
                }
                else {
                    echo 'window.WorldpayMagento.loadThreeDS("'. Mage::getUrl('worldpay/threeDS') .'")';
                }
                exit;
            }
            else {
                if (isset($response['paymentStatusReason'])) {
                    throw new Exception($response['paymentStatusReason']);
                } else {
                    throw new Exception(print_r($response, true));
                }
            }
        }
        catch (Exception $e) {

            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $logger->log($e->getMessage());
            Mage::throwException('Payment failed, please try again later ' . $e->getMessage());
        }
        return $this;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        $session = Mage::getSingleton('core/session');
        if ($session->getData('wp_3dsSuccess')) {
            $this->complete3DSOrder($payment, $amount);
        } else {
            $payment->setAdditionalInformation('payment_type', 'authorize');
            $this->createOrder($payment, $amount, true);
        }
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $logger = Mage::helper('worldpay/logger');
        $session = Mage::getSingleton('core/session');
        if ($session->getData('wp_3dsSuccess')) {
           $this->complete3DSOrder($payment, $amount);
        } else {
            $worldpayOrderCode = $payment->getData('last_trans_id');
            if ($worldpayOrderCode) {
                $worldpay = $this->setupWorldpay();
                try {
                    $authorizationTransaction = $payment->getAuthorizationTransaction();
                    $worldpay->captureAuthorisedOrder($authorizationTransaction->getTxnId(), $amount*100);
                    $payment->setAdditionalInformation("worldpayOrderCode", $authorizationTransaction->getTxnId());
                    $payment->setShouldCloseParentTransaction(1)
                    ->setIsTransactionClosed(1);
                    $logger->log('Capture Order: ' . $session->getData('wp_orderCode') . ' success');
                } 
                catch (Exception $e) {
                    $logger->log('Capture Order: ' . $session->getData('wp_orderCode') . ' failed with ' . $e->getMessage());
                    Mage::throwException('Payment failed, please try again later ' . $e->getMessage());
                }
            } else {
                $payment->setAdditionalInformation('payment_type', 'capture');
                return $this->createOrder($payment, $amount, false);
            }
        }
    }

    public function getConfigPaymentAction()
    {
        $paymentAction = Mage::getStoreConfig('payment/worldpay_cc/payment_action', Mage::app()->getStore()->getStoreId());
        return empty($paymentAction) ? true : $paymentAction;
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
            $worldpay = $this->setupWorldpay();
            try {
                $logger = Mage::helper('worldpay/logger');
                $worldpay->refundOrder($payment->getAdditionalInformation("worldpayOrderCode"), $amount * 100);
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
        $worldpayOrderCode = $payment->getData('last_trans_id');
        $worldpay = $this->setupWorldpay();
        if ($worldpayOrderCode) {
            try {
                $worldpay->cancelAuthorisedOrder($worldpayOrderCode);
            }
            catch (Exception $e) {
                Mage::throwException('Void failed, please try again later ' . $e->getMessage());
            }
        }
        return true;
    }

    public function authorise3DSOrder($paRes)
    {
        $logger = Mage::helper('worldpay/logger');
        $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());
        // if ($mode == 'Test Mode') {
        //     $paRes = 'ERROR';
        // }
        $session = Mage::getSingleton('core/session');
        $worldpay = $this->setupWorldpay();
        $logger->log('Authorising 3DS Order: ' . $session->getData('wp_orderCode') . ' with paRes: ' . $paRes);
        $response = $worldpay->authorise3DSOrder($session->getData('wp_orderCode'), $paRes);
        if (isset($response['paymentStatus']) && ($response['paymentStatus'] == 'SUCCESS' || $response['paymentStatus'] == 'AUTHORIZED')) {
           $session->setData('wp_3dsSuccess', true);
           $logger->log('Order: ' . $session->getData('wp_orderCode') . ' 3DS authorised successfully');
           return true;
        } else {
            $logger->log('Order: ' . $session->getData('wp_orderCode') . ' 3DS failed authorising');
            Mage::throwException('There was a problem authorising 3DS order');
        }
    }

    public function complete3DSOrder($payment, $authorise) {
        $logger = Mage::helper('worldpay/logger');
        $session = Mage::getSingleton('core/session');
        if ($session->getData('wp_3dsSuccess')) {
            $logger->log('Completing 3DS Order: ' . $session->getData('wp_orderCode'));
            $payment->setIsTransactionClosed(0);
            $payment->setSkipOrderProcessing(true);
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setAdditionalInformation("worldpayOrderCode", $session->getData('wp_orderCode'));
            $payment->setLastTransId($session->getData('wp_orderCode'));
            $payment->setTransactionId($session->getData('wp_orderCode'));
            $session->setData('wp_3dsSuccess', false);
            $session->setData('wp_orderCode', false);
            return true;
        } else {
            return false;
        }
    }

    public function getCardsOnFile() {
        $cardsOnFileEnabled = Mage::getStoreConfig('payment/worldpay_cc/card_on_file', Mage::app()->getStore()->getStoreId());
        if ($cardsOnFileEnabled) {
            $worldpay = $this->setupWorldpay();

            if (Mage::app()->getStore()->isAdmin()) {
               $customerData = Mage::getSingleton('adminhtml/session_quote')->getCustomer();
            } else {
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
            }
            $saved_cards = Mage::getModel('worldpay/payment')->getCollection()->addFieldToFilter('customer_id', $customerData->getId());
            
            $storedCards = array();

            foreach ($saved_cards as $card) {
               try {
                    $cardDetails = $worldpay->getStoredCardDetails($card->getToken());
                }
                catch (Exception $e) {
                    // Delete expired tokens
                    if ($e->getCustomCode() == 'TKN_NOT_FOUND') {
                        $card->delete();
                    }
                }  
                if (isset($cardDetails['maskedCardNumber'])) {
                     $storedCards[] = array(
                        'number' => $cardDetails['maskedCardNumber'],
                        'cardType' => $cardDetails['cardType'],
                        'id' => $card->getId(),
                        'token' => $card->getToken()
                    );
                }
               
            }
            return $storedCards;
        }
        return false;
    }

    public function removeCard($id) {
        if ($id) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $token_exists = Mage::getModel('worldpay/payment')->getCollection()->
                addFieldToFilter('customer_id', $customerData->getId())->
                addFieldToFilter('id', $id)->
                getFirstItem();
            if (isset($token_exists['token'])) {
                $token_exists->delete();
            } else {
                return false;
            }
            return true;
        }
        return false;
    }
}
