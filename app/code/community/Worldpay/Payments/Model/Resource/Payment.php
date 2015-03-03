<?php

class Worldpay_Payments_Model_Resource_Payment extends Mage_Core_Model_Mysql4_Abstract {

	protected function _construct() {
		$this->_init('worldpay/payment', 'id');
	}

}

