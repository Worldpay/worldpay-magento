<?php

class Worldpay_Payments_Model_Syncbutton extends Varien_Event_Observer
{
    /**
     * @param $observer
     */
    public function core_block_abstract_to_html_before($observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {

        	$orderId = Mage::app()->getRequest()->getParam('order_id');
	        $order = Mage::getModel('sales/order')->load($orderId);
	        $payment = $order->getPayment();
            if (substr($payment->getMethod(), 0, 9) == 'worldpay_') {
	            $block->addButton('wp_sync_btn', array(
	                'label' => 'Sync with Worldpay',
	                'onclick' => 'setLocation(\'' . Mage::helper("adminhtml")->getUrl('worldpay/sync/manual/order_id/' . $orderId) . '\')',
	                'class' => 'go'
	            ));
		    }
        }
    }
}

