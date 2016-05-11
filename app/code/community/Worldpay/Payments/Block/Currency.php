<?php
class Worldpay_Payments_Block_Currency
    extends Mage_Core_Block_Html_Select
{
    public function _toHtml()
    {
        $options = Mage::getSingleton('adminhtml/system_config_source_currency')
            ->toOptionArray();
        foreach ($options as $option) {
            $this->addOption($option['value'], $option['value']);
        }

        return parent::_toHtml();
    }

    public function setInputName($value)
    {
        return $this->setName($value);
    }
}