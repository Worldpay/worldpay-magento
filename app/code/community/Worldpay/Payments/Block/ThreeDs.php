<?php
class Worldpay_Payments_Block_ThreeDs extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
    	exit;
        parent::_construct();
        $this->setTemplate('worldpay/3ds.phtml');
    }

    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    public function getRedirectUrl() {
    	$session = Mage::getSingleton('core/session');
    	return $session->getData('wp_redirectURL');
    }

    public function getOneTimeToken() {
    	$session = Mage::getSingleton('core/session');
    	print_r($session);
    	exit;
    	return $session->getData('wp_oneTime3DsToken');
    }
}
