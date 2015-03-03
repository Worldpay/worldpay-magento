<?php

class Worldpay_Payments_Helper_Logger
{

    private $is_enabled;
    private $log_file;

    public function __construct()
    {
        $this->setIsEnabled(Mage::getStoreConfig('payment/worldpay/enable_logging', Mage::app()->getStore()->getStoreId()));
        $this->setLogFile('worldpay.log');
    }

    public function log($message)
    {
        if ($this->getIsEnabled() == '1') {
            Mage::log($message, null, $this->getLogFile());

            return true;
        }

        return false;
    }

    public function getIsEnabled()
    {
        return $this->is_enabled;
    }
    public function getLogFile()
    {
        return $this->log_file;
    }
    public function setIsEnabled($data)
    {
        $this->is_enabled = $data;

        return $this;
    }
    public function setLogFile($data)
    {
        $this->log_file = $data;

        return $this;
    }
}
