<?php

abstract class Worldpay_Payments_Controller_AbstractController extends Mage_Core_Controller_Front_Action
{

    protected $_model;

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }
}
