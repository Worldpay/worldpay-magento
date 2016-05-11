<?php

class Worldpay_Payments_Model_PaymentMethods_Przelewy24 extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_przelewy24';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_przelewy24Form';
    protected $_isGateway = true;
}

