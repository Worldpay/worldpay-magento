<?php

class Worldpay_Payments_Model_PaymentMethods_Mistercash extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_mistercash';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_mistercashForm';
    protected $_isGateway = true;
}

