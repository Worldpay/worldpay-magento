<?php

class Worldpay_Payments_Model_PaymentMethods_Giropay extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_giropay';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_giropayForm';
    protected $_isGateway = true;
}

