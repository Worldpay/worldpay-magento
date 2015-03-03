<?php
// Class for compatiblity with 1.4.2
class Worldpay_Payments_Model_PaymentMethods_Dummy extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'dummy';
	protected $_canUseInternal = false;

	public function isAvailable($quote=null)
    {
		return false;
	}
}

