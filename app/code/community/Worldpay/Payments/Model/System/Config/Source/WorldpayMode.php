<?php

	class Worldpay_Payments_Model_System_Config_Source_WorldpayMode {

		public function toOptionArray() {
			return array(
				array('value' => 'Test Mode', 'label' => Mage::Helper('adminhtml')->__('Test Mode')),
				array('value' => 'Live Mode', 'label' => Mage::Helper('adminhtml')->__('Live Mode'))
				);
		}

	}
