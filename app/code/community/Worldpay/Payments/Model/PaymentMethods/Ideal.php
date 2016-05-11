<?php

class Worldpay_Payments_Model_PaymentMethods_Ideal extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_ideal';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_idealForm';
    protected $_isGateway = true;
}

