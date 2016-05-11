<?php

class Worldpay_Payments_ThreeDSController extends Worldpay_Payments_Controller_AbstractController
{
    /**
     *
     */
    public function indexAction()
    {
        $session = Mage::getSingleton('core/session');
        $this->loadLayout();
        $block = $this->getLayout()->getBlock('worldpay_threeDs');

        $token = $session->getData('wp_oneTime3DsToken');
        $block->setOneTimeToken($token);
        $block->setRedirectUrl($session->getData('wp_redirectURL'));
        $block->setCurrentUrl(Mage::getUrl('worldpay/threeDS/return', array('_secure'=>true)));

        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function returnAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $orderId = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        $payment = $order->getPayment();
        try {
            $result = Mage::getModel('worldpay/paymentMethods_creditCards')->authorize3DSOrder($_POST['PaRes']);
            if ($result) {
                Mage::getModel('worldpay/paymentMethods_creditCards')->complete3DSOrder($payment, $order->getGrandTotal(), $order);
            }
            $session->setLastSuccessQuoteId($order->getQuoteId());
            $session->setLastQuoteId($order->getQuoteId());
            $session->setLastOrderId($order->getId());

            $url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));

            echo '<script>parent.window.WorldpayMagento.completeCheckoutThreeDS("'. $url .'")</script>';
        }
        catch (Exception $e) {
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, '3DS Authorize failed')->save();
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_DECLINED);
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            $quote->setIsActive(true)->save();
            $this->outputError($e->getMessage());
        }
    }

    public function checkoutAction()
    {

        $threeDS = Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId());

        if (!$threeDS) {
            echo '<script>parent.window.WorldpayMagento.completeCheckoutThreeDS();</script>';
            return;
        }
        // Create Worldpay 3ds order
        $session = Mage::getModel('checkout/session');
        $quote = Mage::getModel('checkout/cart')->getQuote();

        $quote->collectTotals()->getPayment()->setMethod('worldpay_cc');
        $quote->getShippingAddress()->setCollectShippingRates(true);

        $quote->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        $quote->setIsActive(false)->save();

        $increment_id = $service->getOrder()->getRealOrderId();

        $order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

        $session->setLastRealOrderId($increment_id);

        try {

            $paymentAction = Mage::getStoreConfig('payment/worldpay_cc/payment_action', Mage::app()->getStore()->getStoreId());
            $authorize = false;
            if ($paymentAction == 'authorize') $authorize = true;
            
            $needsCompletion = Mage::getModel('worldpay/paymentMethods_creditCards')->createOrder($order->getPayment(), $order->getGrandTotal(), $authorize);

            if ($needsCompletion === false) {
                $session->setLastSuccessQuoteId($order->getQuoteId());
                $session->setLastQuoteId($order->getQuoteId());
                $session->setLastOrderId($order->getId());

                $url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));

                echo '<script>parent.window.WorldpayMagento.completeCheckoutThreeDS("'. $url .'")</script>';
            } else {
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('worldpay/threeDS/index', array('_secure'=>true)));
            }
        }
        catch (Exception $e) {
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, '3DS Authorize failed')->save();
            //$payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_DECLINED);
            $quote->setIsActive(true)->save();
            $this->outputError($e->getMessage());
        }
    }

    private function outputError($string) {
        echo '<script>parent.window.WorldpayMagento.threeDSError("'. htmlentities($string) .'", "'. Mage::getUrl('checkout/multishipping/backtobilling', array('_secure'=>true))  .'");</script>';
    }
}
