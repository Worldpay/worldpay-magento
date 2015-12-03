<?php

abstract class Worldpay_Payments_Model_PaymentMethods_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_response;

    protected static $_postData;
    protected static $_type;
    protected static $_model;

    public function setupWorldpay() {
        require_once(Mage::getModuleDir('', 'Worldpay_Payments')  . DS .  'lib'  . DS . 'worldpay.php');

        $mode = Mage::getStoreConfig('payment/worldpay_mode', Mage::app()->getStore()->getStoreId());

        $sslDisabled = Mage::getStoreConfig('payment/worldpay/ssl_disabled', Mage::app()->getStore()->getStoreId());

        if ($mode == 'Test Mode') {
            $service_key = Mage::getStoreConfig('payment/worldpay/test_service_key', Mage::app()->getStore()->getStoreId());
        }
        else {
            $service_key = Mage::getStoreConfig('payment/worldpay/live_service_key', Mage::app()->getStore()->getStoreId());
        }

        $worldpay = new Worldpay($service_key);
        $worldpay->endpoint = Mage::getStoreConfig('payment/worldpay/api_endpoint', Mage::app()->getStore()->getStoreId());;
        if (! $worldpay->endpoint) {
            $worldpay->endpoint = 'https://api.worldpay.com/v1/';
        }
        if ($mode == 'Test Mode' && $sslDisabled) {
            $worldpay->disableSSLCheck(true);
        }

        return $worldpay;
    }

    public function createOrder(Varien_Object $payment, $amount, $authorize) {
        $store_id = Mage::app()->getStore()->getStoreId();

        $logger = Mage::helper('worldpay/logger');

        if ($payment->getOrder()) {
            $orderId = $payment->getOrder()->getIncrementId();
            $order = $payment->getOrder();
        } else  {
           $quote = $payment->getQuote();
           $orderId = $quote->getReservedOrderId();
           $quote->save();
        }
        
        $session = Mage::getSingleton('core/session');
        $token = $session->getData('payment_token');
        $savedCard = $session->getData('saved_card');

        $logger->log('Begin create order');

        $session->setData('wp_3dsSuccess', false);
        $session->setData('wp_orderCode', false);

        $worldpay = $this->setupWorldpay();

        $checkout = Mage::getSingleton('checkout/session')->getQuote();

        $billing = $checkout->getBillingAddress();

        $order_description = Mage::getStoreConfig('payment/worldpay/description', $store_id);

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

            $settlementCurrency = Mage::getStoreConfig('payment/worldpay/settlementcurrency', Mage::app()->getStore()->getStoreId());

            $createOrderRequest = array(
                'token' => $token,
                'orderDescription' => $order_description,
                'amount' => $amount*100,
                'currencyCode' => $currency_code,
                'name' => $name,
                'billingAddress' => $billing_address,
                'customerOrderCode' => $orderId,
                'settlementCurrency' => $settlementCurrency,
                'successUrl' => Mage::getUrl('worldpay/apm/success', array('_secure'=>true)),
                'pendingUrl' => Mage::getUrl('worldpay/apm/pending', array('_secure'=>true)),
                'failureUrl' => Mage::getUrl('worldpay/apm/failure', array('_secure'=>true)),
                'cancelUrl' => Mage::getUrl('worldpay/apm/cancel', array('_secure'=>true))
            );

            $logger->log('Order Request: ' .  print_r($createOrderRequest, true));
            $response = $worldpay->createApmOrder($createOrderRequest);
            $logger->log('Order Response: ' .  print_r($response, true));
            
            if ($response['paymentStatus'] === 'SUCCESS') {
                $this->setStore($payment->getOrder()->getStoreId());
                $logger->log('Order: ' .  $response['orderCode'] . ' SUCCESS');
                $payment->setStatus(self::STATUS_APPROVED);
                $payment->setAmount($amount);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
                $payment->setShouldCloseParentTransaction(1)
                ->setIsTransactionClosed(1)
                ->registerCaptureNotification($amount);
            }
            else if ($response['paymentStatus'] == 'PRE_AUTHORIZED') {
                $logger->log('Order: ' .  $response['orderCode'] . ' PRE_AUTHORIZED');
                $payment->setAmount($amount);
                $payment->setAdditionalInformation("worldpayOrderCode", $response['orderCode']);
                $payment->setLastTransId($orderId);
                $payment->setTransactionId($response['orderCode']);
                $payment->setIsTransactionClosed(false);
                $session->setData('wp_redirectURL', $response['redirectURL']);
                $session->setData('wp_orderCode', $response['orderCode']);
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

    public function isInitializeNeeded()
    {
        return true;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    public function getOrderPlaceRedirectUrl() {
        $logger = Mage::helper('worldpay/logger');
        $session = Mage::getSingleton('core/session');
        $quote = Mage::getModel('checkout/session')->getQuote();
        $quoteData= $quote->getData();
        $grandTotal=$quoteData['grand_total'];
        $this->createOrder($quote->getPayment(), $grandTotal, false);
        return  Mage::getUrl('worldpay/apm/redirect', array('_secure'=>true));
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function getConfigPaymentAction()
    {
        return 'authorize';
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

        Mage::throwException('No matching order found in Worldpay to refund. Please visit your Worldpay dashboard and refund the order manually.');
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

    public function cancel(Varien_Object $payment)
    {
        $worldpayOrderCode = $payment->getData('last_trans_id');
        $worldpay = $this->setupWorldpay();
        if ($worldpayOrderCode) {
            try {
                $worldpay->cancelAuthorisedOrder($worldpayOrderCode);
            }
            catch (Exception $e) {
                Mage::throwException('Cancel failed, please try again later ' . $e->getMessage());
            }
        }
        return true;
    }

    public function isAvailable($quote=null)
    {
        $store_id      = is_object($quote) ? $quote->getStoreId() : Mage::app()->getStore()->getStoreId();
        $methodEnabled = (boolean) Mage::getStoreConfig('payment/'.$this->_code.'/enabled', $store_id);

        return $methodEnabled;
    }

    public function assignData($data)
    {
        parent::assignData($data);
        $session = Mage::getSingleton('core/session');
        $session->setData('payment_token', $data->token);
        return $this;
    }

    public function updateOrder($status, $orderCode, $order, $payment, $amount) {
        
        $logger = Mage::helper('worldpay/logger');
       

        if ($status === 'REFUNDED' || $status === 'SENT_FOR_REFUND') {
            $payment
            ->setTransactionId($orderCode)
            ->setParentTransactionId($orderCode)
            ->setIsTransactionClosed(true)
            ->registerRefundNotification($amount);
            $logger->log('Order '. $orderCode .' REFUNDED');
        }
        else if ($status === 'FAILED') {

            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_DECLINED);

            $logger->log('Order '. $orderCode .' FAILED');
        }
        else if ($status === 'SETTLED') {
            $logger->log('Order '. $orderCode .' SETTLED');
        }
        else if ($status === 'SUCCESS') {
            if ($payment->getData('last_trans_id') != $orderCode) {
                $payment->setTransactionId($orderCode)
                ->setShouldCloseParentTransaction(1)
                ->setIsTransactionClosed(0)
                ->registerCaptureNotification($amount);

                $invoice = $payment->getCreatedInvoice();
                if ($invoice && !$order->getEmailSent()) {
                    $order->sendNewOrderEmail()->addStatusHistoryComment('Notified customer about invoice ' . $invoice->getIncrementId())
                    ->setIsCustomerNotified(true)
                    ->save();
                }
                $logger->log('Order '. $orderCode .' SUCCESS');
            }
        }
        else {
            // Other status, magento doesn't handle.
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_UNKNOWN);
            $order->addStatusHistoryComment('Unknown Worldpay Payment Status: ' . $status . ' for ' . $orderCode);
            $order->setSate(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $status);
        }
        $order->save();
    }
}
