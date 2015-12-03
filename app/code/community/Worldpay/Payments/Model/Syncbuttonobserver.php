<?php

class Worldpay_Syncbutton_Model_Observer extends Varien_Event_Observer
{
    /**
     * @param $observer
     */
    public function core_block_abstract_to_html_before($observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
            $orderId = Mage::app()->getRequest()->getParam('order_id');
            $block->addButton('my_button', array(
                'label' => Mage::helper('bewareofrewrites_addorderbutton')->__('My Button Label'),
                'onclick' => 'setLocation(\'' . Mage::helper("adminhtml")->getUrl('adminhtml/bewareofrewrites_addorderbutton/myaction/order_id/' . $orderId) . '\')',
                'class' => 'go'
            ));
        }
    }
}

