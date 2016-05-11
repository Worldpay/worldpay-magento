<?php

class Worldpay_Payments_Model_PaymentMethods_Yandex extends Worldpay_Payments_Model_PaymentMethods_Abstract {

	protected $_code = 'worldpay_yandex';
	protected $_canUseInternal = false;
	protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
	protected $_formBlockType = 'worldpay/payment_yandexForm';
    protected $_isGateway = true;
}

