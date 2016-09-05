<?php

class Worldpay_Payments_ApmController extends Worldpay_Payments_Controller_AbstractController
{
    /**
     *
     */

    protected function _initCheckout()
    {
        $session = Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');
            Mage::throwException('Error');
        }
        $checkout = Mage::getSingleton($this->_checkoutType, array(
            'config' => $this->_config,
            'quote'  => $quote,
        ));

        return $checkout;
    }

    public function redirectAction() {
        $logger = Mage::helper('worldpay/logger');
        $orderSession = Mage::getSingleton('checkout/session');
        $session = Mage::getSingleton('core/session');
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderSession->getLastRealOrderId());
        $order->setExtOrderId($session->getData('wp_orderCode'));
        $payment = $order->getPayment();
        $payment->setAdditionalInformation("worldpayOrderCode", $session->getData('wp_orderCode'));
        $order->save();
        $logger->log('Redirect to: ' . $session->getData('wp_redirectURL'));
        $this->_redirectUrl($session->getData('wp_redirectURL'));
    }

    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
        $session->setLastSuccessQuoteId($order->getQuoteId());

        if (!$order->getEmailSent()) {
            $order->sendNewOrderEmail()->addStatusHistoryComment('Notified customer about order id ' . $session->getLastRealOrderId())
                ->setIsCustomerNotified(true)
                ->save();
        }

        $this->_redirect('checkout/onepage/success');
    }

    public function pendingAction()
    {
        $session = Mage::getSingleton('checkout/session');
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }

    public function failureAction()
    {
        $session = Mage::getSingleton('checkout/session');
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            Mage::helper('paypal/checkout')->restoreQuote();
        }
        Mage::getSingleton('core/session')->addError('Payment failed, please try again');
        $this->_redirect('checkout/cart');
    }

    public function cancelAction()
    {   
        $session = Mage::getSingleton('checkout/session');
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            Mage::helper('paypal/checkout')->restoreQuote();
        }
        Mage::getSingleton('core/session')->addError('Payment canceled');
        $this->_redirect('checkout/cart');
    }

    private function outputError($string) {
        echo '<script>parent.window.WorldpayMagento.threeDSError("'. $string .'", "'. Mage::getUrl('checkout/multishipping/backtobilling')  .'");</script>';
    }
}
