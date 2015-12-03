<?php
class Worldpay_Payments_Block_Saved extends Mage_Core_Block_Template
{
    /**
     * Return whether the customer has saved details
     *
     * @param bool $type
     *
     * @return bool
     */
    public function hasSavedDetails($type = false)
    {
        return Mage::getSingleton('gene_braintree/saved')->hasType($type);
    }

    /**
     * Retrieve those said saved details
     *
     * @param bool $type
     *
     * @return array
     */
    public function getSavedDetails($type = false)
    {
        return Mage::getSingleton('gene_braintree/saved')->getSavedMethodsByType($type);
    }

}
