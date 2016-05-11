<?php
class Worldpay_Payments_Block_Sitecodes
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected $_itemRenderer;

    public function _prepareToRender()
    {
        $this->addColumn('currency', array(
            'label' => 'Acceptance Currency',
            'renderer' => $this->_getCurrencyRenderer(),
        ));
        $this->addColumn('settlement_currency', array(
            'label' => 'Settlement Currency',
            'renderer' => $this->_getSettlementRenderer()
        ));
        $this->addColumn('site_code', array(
            'label' => 'Sitecode',
            'style' => 'width:100px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = 'Add';
    }

    protected function  _getCurrencyRenderer() 
    {
        if (!$this->_itemCurrencyRenderer) {
            $this->_itemCurrencyRenderer = $this->getLayout()->createBlock(
                'worldpay/currency', '',
                array('is_render_to_js_template' => true)
            );
        }
        return $this->_itemCurrencyRenderer;
    }

    protected function  _getSettlementRenderer() 
    {
        if (!$this->_itemSettlementRenderer) {
            $this->_itemSettlementRenderer = $this->getLayout()->createBlock(
                'worldpay/settlementCurrency', '',
                array('is_render_to_js_template' => true)
            );
        }
        return $this->_itemSettlementRenderer;
    }

    protected function _prepareArrayRow(Varien_Object $row)
    {
        $row->setData(
            'option_extra_attr_' . $this->_getSettlementRenderer()
                ->calcOptionHash($row->getData('settlement_currency')),
            'selected="selected"'
        );
        $row->setData(
            'option_extra_attr_' . $this->_getCurrencyRenderer()
                ->calcOptionHash($row->getData('currency')),
            'selected="selected"'
        );
    }
}