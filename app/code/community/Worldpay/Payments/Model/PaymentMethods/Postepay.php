<?php

class Worldpay_Payments_Model_PaymentMethods_Postepay extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_postepay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_postepayForm';
    protected $_isGateway = true;
}

