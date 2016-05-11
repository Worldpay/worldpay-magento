<?php

class Worldpay_Payments_Model_PaymentMethods_Sofort extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_sofort';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_sofortForm';
    protected $_isGateway = true;
}

