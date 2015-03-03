<?php

class Worldpay_Payments_NotificationController extends Worldpay_Payments_Controller_AbstractController
{
    /**
     *
     */
    public function indexAction()
    {
        $logger = Mage::helper('worldpay/logger');

        if (!$this->getRequest()->isPost()) {
            $logger->log('Auth/Capture notifications must be POST');
            /**
             * send OK to whoever tries to play with us
             */
            echo '[OK]';
            return;
        }

        require_once(Mage::getModuleDir('', 'Worldpay_Payments')  . DS .  'lib'  . DS . 'worldpay.php');

        try {
            $notification = Worldpay::processWebhook();
        }
        catch(Exception $e) {
            http_response_code(500);
            $logger->log($e->getMessage());
            echo '[OK]';
            return;
        }

        $payment = Mage::getModel('sales/order_payment')->getCollection()
            ->addAttributeToFilter('last_trans_id', array('eq' => $notification['orderCode']))->getFirstItem();

        if (!$payment) {
            http_response_code(500);
            $logger->log('Payment '. $notification['orderCode'] .' not found!');
            echo '[OK]';
            return;
        }


        $order = Mage::getModel('sales/order')
                                ->load($payment->getParentId(), 'entity_id');

        if (!$order) {
            http_response_code(500);
            $logger->log('Order '. $notification['orderCode'] .' not found!');
            echo '[OK]';
            return;
        }

        $payment = $order->getPayment();
        $amount = $order->getGrandTotal();

        if ($notification['paymentStatus'] === 'REFUNDED') {
            $payment->setIsTransactionClosed(1);
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_VOID);
            $order->setSate(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, 'Payment Refunded');
        }
        else if ($notification['paymentStatus'] === 'FAILED') {

            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_DECLINED);
        }
        else if ($notification['paymentStatus'] === 'SETTLED') {
            $payment->setIsTransactionClosed(1);
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_SUCCESS);
        }
        else if ($notification['paymentStatus'] === 'SUCCESS') {

            $payment->setTransactionId($notification['orderCode'])
            ->setShouldCloseParentTransaction(1)
            ->setIsTransactionClosed(1)
            ->registerCaptureNotification($amount);
        }
        else {
            // Other status, magento doesn't handle.
            $payment->setStatus(Worldpay_Payments_Model_PaymentMethods_Abstract::STATUS_UNKNOWN);
            $order->addStatusHistoryComment('Worldpay Payment Status:' . $notification['paymentStatus']);
            $order->setSate(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $notification['paymentStatus']);
        }
        $order->save();
        // give worldpay confirmation
        echo '[OK]';
    }
}
