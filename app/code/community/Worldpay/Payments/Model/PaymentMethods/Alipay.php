<?php

class Worldpay_Payments_Model_PaymentMethods_Alipay extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_alipay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_alipayForm';
    protected $_isGateway = true;
}

