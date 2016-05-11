<?php

class Worldpay_Payments_Model_PaymentMethods_Paysafecard extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_paysafecard';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_paysafecardForm';
    protected $_isGateway = true;
}

