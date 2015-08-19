<?php
class Worldpay_Payments_SavedController extends Worldpay_Payments_Controller_AbstractController
{
    public function indexAction()
    {
        $session = Mage::getSingleton('core/session');
        $this->loadLayout();
        $block = $this->getLayout()->getBlock('worldpay_savedcards');
        $block->setCardsOnFile(Mage::getModel('worldpay/paymentMethods_creditCards')->getCardsOnFile()); 
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function removeAction()
    {
        $id = $this->getRequest()->getParam('id');
        $result = Mage::getModel('worldpay/paymentMethods_creditCards')->removeCard($id); 
        if (!$result) {
            Mage::getSingleton('core/session')->addError('Failed to remove card, please try again later');
        } else {
             Mage::getSingleton('core/session')->addSuccess('Card removed');
        }
       Mage::app()->getResponse()->setRedirect(Mage::getUrl('worldpay/saved'));
    }
}
