<?php

class Worldpay_Payments_Model_PaymentMethods_PayPal extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_paypal';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_paypalForm';
    protected $_isGateway = true;
}

