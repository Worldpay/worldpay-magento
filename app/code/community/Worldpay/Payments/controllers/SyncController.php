<?php

class Worldpay_Payments_SyncController extends Worldpay_Payments_Controller_AbstractController
{
    /**
     *
     */
    public function manualAction() {
        $logger = Mage::helper('worldpay/logger');
        $orderId = $this->getRequest()->getParam('order_id');

        if (!$orderId) {
            Mage::throwException('No id found!');
        }

        $order = Mage::getModel('sales/order')->load($orderId);

        $payment = $order->getPayment();
        $orderCode = $order['ext_order_id'];
        if (!$orderCode) {
            $orderCode = $payment->getLastTransId();
            if (!$orderCode) {
                http_response_code(500);
                $logger->log('Order '. $orderId .' not found!');
                $this->_redirectUrl(Mage::helper("adminhtml")->getUrl('adminhtml/sales_order/view/order_id/' . $orderId));
                return;
            }
        }

        $logger->log('Manual Sync of ' . $orderCode);
        $worldpay = Mage::getModel('worldpay/paymentMethods_creditCards')->setupWorldpay();
        try {
            $notification = $worldpay->getOrder($orderCode);
        }
        catch(Exception $e) {
            http_response_code(500);
            $logger->log('Error validating webhook');
            $this->_redirectUrl(Mage::helper("adminhtml")->getUrl('adminhtml/sales_order/view/order_id/' . $orderId));
            return;
        }

        $amount = false;
        if ($notification['amount']) {
            $amount = $notification['amount']/100;
        }

        Mage::getModel('worldpay/paymentMethods_creditCards')->updateOrder($notification['paymentStatus'], $notification['orderCode'], $order, $payment, $amount);
        Mage::app()->getResponse()->setRedirect(Mage::helper("adminhtml")->getUrl('adminhtml/sales_order/view/order_id/' . $orderId));
    }
}
