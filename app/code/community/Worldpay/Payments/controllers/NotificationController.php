<?php

class Worldpay_Payments_NotificationController extends Worldpay_Payments_Controller_AbstractController
{
    public function indexAction()
    {
        $logger = Mage::helper('worldpay/logger');

        if (!$this->getRequest()->isPost()) {
            $logger->log('Auth/Capture notifications must be POST');
            echo '[OK]';
            return;
        }


        $worldpay = Mage::getModel('worldpay/paymentMethods_creditCards')->setupWorldpay();

        try {
            $response = file_get_contents('php://input');
            $originalNotification = json_decode($response, true);
        }
        catch(Exception $e) {
            http_response_code(500);
            $logger->log('Error getting webhook');
            echo '[OK]';
            return;
        }

        try {
            $notification = $worldpay->getOrder($originalNotification['orderCode']);
        }
        catch(Exception $e) {
            http_response_code(500);
            $logger->log('Error validating webhook');
            echo '[OK]';
            return;
        }

        if ($originalNotification['paymentStatus'] != $notification['paymentStatus']) {
            http_response_code(500);
            $logger->log('Order status does not match');
            echo '[OK]';
            return;
        }

        //Get order by quote id, add payment as success
        $order = Mage::getModel('sales/order')->loadByAttribute('ext_order_id', $notification['orderCode']);
        
        $payment = $order->getPayment();

        if (!$payment->getId()) {
            $payment = Mage::getModel('sales/order_payment')->getCollection()
            ->addAttributeToFilter('last_trans_id', array('eq' => $notification['orderCode']))->getFirstItem();
            $order = Mage::getModel('sales/order')
                                ->load($payment->getParentId(), 'entity_id');
        }

        if (!$payment->getId()) {
            http_response_code(500);
            $logger->log('Payment '. $notification['orderCode'] .' not found!');
            echo '[OK]';
            return;
        }

        if (!$order) {
            http_response_code(500);
            $logger->log('Order '. $notification['orderCode'] .' not found!');
            echo '[OK]';
            return;
        }
        $amount = false;

        if ($notification['amount']) {
            $amount = $notification['amount']/100;
        }

        Mage::getModel('worldpay/paymentMethods_creditCards')->updateOrder($notification['paymentStatus'], $notification['orderCode'], $order, $payment, $amount);
        
        // give worldpay confirmation
        echo '[OK]';
    }
}
