<?php

	class Worldpay_Payments_Model_System_Config_Source_PaymentAction {

		public function toOptionArray() {
			return array(
				array('value' => 'authorize', 'label' => Mage::Helper('adminhtml')->__('Authorize Only')),
				array('value' => 'authorize_capture', 'label' => Mage::Helper('adminhtml')->__('Authorize and Capture'))
				);
		}

	}
