<?php

class Worldpay_Payments_Model_PaymentMethods_Qiwi extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_qiwi';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_qiwiForm';
    protected $_isGateway = true;
}

