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
        $block->setCurrentUrl(Mage::getUrl('worldpay/threeDS/return'));

        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function returnAction()
    {
        try {
            $result = Mage::getModel('worldpay/paymentMethods_creditCards')->authorise3DSOrder($_POST['PaRes']);
            echo '<script>parent.window.WorldpayMagento.threeDSAuthorised()</script><a href="'. Mage::getUrl('checkout/multishipping/overview') .'">Return to checkout</a>';
        }
        catch (Exception $e) {
            $this->outputError($e->getMessage());
        }

    }

    public function checkoutAction()
    {
        if (!Mage::getStoreConfig('payment/worldpay_cc/use3ds', Mage::app()->getStore()->getStoreId())) {
            echo '<script>parent.window.WorldpayMagento.threeDSAuthorised()</script>';
            exit;
        }
        $quote = Mage::getModel('checkout/session')->getQuote();
        $quoteData= $quote->getData();
        $grandTotal=$quoteData['grand_total'];
        try {
            $paymentAction = Mage::getStoreConfig('payment/worldpay_cc/payment_action', Mage::app()->getStore()->getStoreId());
            $authorize = false;
            if ($paymentAction == 'authorize') {
                $authorize = true;
            }
            $result = Mage::getModel('worldpay/paymentMethods_creditCards')->createOrder($quote->getPayment(), $grandTotal, $authorize);
            if (!$result) {
                echo 'Error';
            } else {
                Mage::app()->getResponse()->setRedirect(Mage::getUrl('worldpay/threeDS'));
            }
        } catch(Exception $e) {
            echo '<script>parent.window.WorldpayMagento.threeDSError("'. $e->getMessage() .'", "'. Mage::getUrl('checkout/multishipping/backtobilling')  .'");</script>';
        }
    }

    private function outputError($string) {
        echo '<script>parent.window.WorldpayMagento.threeDSError("'. $string .'", "'. Mage::getUrl('checkout/multishipping/backtobilling')  .'");</script>';
    }
}
