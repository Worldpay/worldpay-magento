<?php

class Worldpay_Payments_Model_PaymentMethods_CreditCards extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_cc';
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
    protected $_formBlockType           = 'worldpay/payment_cardForm';

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
                if ($data->savecard && $customerData->getId()) {
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

    public function initialize($paymentAction, $stateObject)
    {
         $threeDS = Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());

        if ($threeDS && !Mage::app()->getStore()->isAdmin()) {
            $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
            $stateObject->setState($state);
            $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            $stateObject->setIsNotified(false);
        }
    }

    public function isInitializeNeeded()
    {
        $threeDS = Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());

        if ($threeDS && !Mage::app()->getStore()->isAdmin()) {
            return true;
        } else {
            return false;
        }
    }

    public function authorize3DSOrder($paRes)
    {
        $logger = Mage::helper('worldpay/logger');
        $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());
        $session = Mage::getSingleton('core/session');
        $worldpay = $this->setupWorldpay();
        $logger->log('Authorising 3DS Order: ' . $session->getData('wp_orderCode') . ' with paRes: ' . $paRes);

        if (!$session->getData('wp_orderCode')) {
            $logger->log('No order id found in session!');
            Mage::throwException('There was a problem authorising your 3DS order');
        }

        $response = $worldpay->authorize3DSOrder($session->getData('wp_orderCode'), $paRes);
        if (isset($response['paymentStatus']) && ($response['paymentStatus'] == 'SUCCESS' || $response['paymentStatus'] == 'AUTHORIZED')) {
           $session->setData('wp_3dsSuccess', true);
           $logger->log('Order: ' . $session->getData('wp_orderCode') . ' 3DS authorized successfully');
           return true;
        } else {
            $logger->log('Order: ' . $session->getData('wp_orderCode') . ' 3DS failed authorising');
            Mage::throwException('There was a problem authorising your 3DS order');
        }
    }

    public function complete3DSOrder($payment, $amount, $order) {
        $logger = Mage::helper('worldpay/logger');
        $session = Mage::getSingleton('core/session');
        if ($session->getData('wp_3dsSuccess')) {
            $logger->log('Completing 3DS Order: ' . $session->getData('wp_orderCode'));

             $paymentAction = Mage::getStoreConfig('payment/worldpay_cc/payment_action', Mage::app()->getStore()->getStoreId());

            if ($paymentAction != 'authorize') {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);

                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
                 
                $transactionSave->save();

                if ($invoice && !$order->getEmailSent()) {
                    $order->sendNewOrderEmail()->addStatusHistoryComment('Notified customer about invoice ' . $invoice->getIncrementId())
                    ->setIsCustomerNotified(true)
                    ->save();
                }
            } else {
                $payment->authorize(true, $amount);
                $order->save();
            }

            $session->setData('wp_3dsSuccess', false);
            $session->setData('wp_orderCode', false);
            $session->setData('wp_3dscompletionNeeded', false);
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

    public function createOrder(Varien_Object $payment, $amount, $authorize) {
        $store_id = Mage::app()->getStore()->getStoreId();

        $logger = Mage::helper('worldpay/logger');
        $logger->log('Begin create order');
        if ($payment->getOrder()) {
            $orderId = $payment->getOrder()->getIncrementId();
            $order = $payment->getOrder();
        } else if (!$orderId) {
            $logger->log('ERROR: No order or order id');
        }
        
        $session = Mage::getSingleton('core/session');
        $token = $session->getData('payment_token');
        $savedCard = $session->getData('saved_card');
        
        $session->setData('wp_3dsSuccess', false);
        $session->setData('wp_orderCode', false);

        $worldpay = $this->setupWorldpay();

        $currency_code = $order->getOrderCurrencyCode();
      
        try {

            $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

            $orderType = 'ECOM';
            $threeDS = Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());
            if (Mage::app()->getStore()->isAdmin()) {
                $orderType = 'MOTO';
                $threeDS = false;
            }

            $orderDetails = $this->getSharedOrderDetails($order, $currency_code);

            if ($threeDS && $mode == 'Test Mode' && $orderDetails['name'] != 'NO 3DS') {
                $orderDetails['name'] = '3D';
            }
            
            $createOrderRequest = array(
                'token' => $token,
                'orderDescription' => $orderDetails['orderDescription'],
                'amount' => $amount*100,
                'currencyCode' => $orderDetails['currencyCode'],
                'siteCode' => $orderDetails['siteCode'],
                'name' => $orderDetails['name'],
                'orderType' => $orderType,
                'is3DSOrder' => $threeDS,
                'authorizeOnly' => $authorize,
                'billingAddress' => $orderDetails['billingAddress'],
                'deliveryAddress' => $orderDetails['deliveryAddress'],
                'customerOrderCode' => $orderId,
                'settlementCurrency' => $orderDetails['settlementCurrency'],
                'shopperIpAddress' => $orderDetails['shopperIpAddress'],
                'shopperSessionId' => $orderDetails['shopperSessionId'],
                'shopperUserAgent' => $orderDetails['shopperUserAgent'],
                'shopperAcceptHeader' => $orderDetails['shopperAcceptHeader'],
                'shopperEmailAddress' => $orderDetails['shopperEmailAddress']
            );

            $logger->log('Order Request: ' .  print_r($createOrderRequest, true));
            $response = $worldpay->createOrder($createOrderRequest);
            $logger->log('Order Response: ' .  print_r($response, true));

            if (($response['paymentStatus'] === 'SUCCESS' || $response['paymentStatus'] == 'AUTHORIZED') && $response['is3DSOrder']) {
                $session->setData('wp_3dsSuccess', true);
                $session->setData('wp_3dscompletionNeeded', true);
                $session->setData('wp_orderCode', $response['orderCode']);
                $session->setData('wp_incrementOrderId', $orderId);
                $session->setData('wp_redirectURL', false);
                $session->setData('wp_oneTime3DsToken', false);

                $payment->setAmount($amount);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setIsTransactionClosed(false);
                $payment->save();

                $order->addStatusHistoryComment('No 3DS redirect: ' . $response['orderCode'])
                ->setIsCustomerNotified(false);
                $order->save();

                $this->complete3DSOrder($payment, $amount, $order);

                return false;
            } else if ($response['paymentStatus'] === 'SUCCESS') {
                $logger->log('Order: ' .  $response['orderCode'] . ' SUCCESS');
                $this->setStore($payment->getOrder()->getStoreId());
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setAmount($amount);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
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
            else if ($response['is3DSOrder'] && $response['paymentStatus'] == 'PRE_AUTHORIZED') {
                $logger->log('Starting 3DS Order: ' .  $response['orderCode']);
                $session->setData('wp_3dsSuccess', false);
                $session->setData('wp_3dscompletionNeeded', true);
                $session->setData('wp_redirectURL', $response['redirectURL']);
                $session->setData('wp_oneTime3DsToken', $response['oneTime3DsToken']);
                $session->setData('wp_orderCode', $response['orderCode']);
                $session->setData('wp_incrementOrderId', $orderId);
                $currentUrl = Mage::helper('core/url')->getCurrentUrl();
                $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);

                $payment->setAmount($amount);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setIsTransactionClosed(false);
                $payment->save();

                $order->addStatusHistoryComment('3DS payment pending authorization: ' . $response['orderCode'])
                    ->setIsCustomerNotified(false);
                $order->save();
                $path = $url->getPath();
                return $this;
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

        if ($session->getData('wp_3dscompletionNeeded')) {
            $payment->setIsTransactionClosed(0);
            $payment->setSkipOrderProcessing(true);
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setShouldCloseParentTransaction(1);
            $payment->setAdditionalInformation("worldpayOrderCode", $session->getData('wp_orderCode'));
            $payment->setLastTransId($session->getData('wp_orderCode'));
            $payment->setTransactionId($session->getData('wp_orderCode'));
            $session->setData('wp_3dscompletionNeeded', false);
            return $this;
        }

        $payment->setAdditionalInformation('payment_type', 'authorize');
        $this->createOrder($payment, $amount, true);
    }

    public function getConfigPaymentAction()
    {
        $paymentAction = Mage::getStoreConfig('payment/worldpay_cc/payment_action', Mage::app()->getStore()->getStoreId());
        return empty($paymentAction) ? true : $paymentAction;
    }

    public function getOrderPlaceRedirectUrl() {
        return false;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $session = Mage::getSingleton('core/session');
        $logger = Mage::helper('worldpay/logger');

        if ($session->getData('wp_3dscompletionNeeded')) {
            $payment->setIsTransactionClosed(0);
            $payment->setSkipOrderProcessing(true);
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setShouldCloseParentTransaction(1);
            $payment->setAdditionalInformation("worldpayOrderCode", $session->getData('wp_orderCode'));
            $payment->setLastTransId($session->getData('wp_orderCode'));
            $payment->setTransactionId($session->getData('wp_orderCode'));
            $session->setData('wp_3dscompletionNeeded', false);
            return $this;
        }

        $worldpayOrderCode = $payment->getData('last_trans_id');
        if ($worldpayOrderCode) {
            $worldpay = $this->setupWorldpay();
            try {
                $authorizationTransaction = $payment->getAuthorizationTransaction();
                if ($authorizationTransaction) {
                    $payment->setAdditionalInformation("worldpayOrderCode", $authorizationTransaction->getTxnId());
                    $worldpayOrderCode = $authorizationTransaction->getTxnId();
                } else {
                     $worldpayOrderCode = $payment->getAdditionalInformation('worldpayOrderCode');
                }
                $worldpay->captureAuthorizedOrder($worldpayOrderCode, $amount*100);
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

