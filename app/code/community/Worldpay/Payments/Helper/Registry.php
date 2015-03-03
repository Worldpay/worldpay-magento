<?php

class Worldpay_Payments_Helper_Registry
{
    public function removeAllData()
    {
        $keys = array(
            'worldpay_payment_id',
            'worldpay_card_number',
            'worldpay_payment_method',
            'worldpay_order_id',
            'worldpay_run_status',
            'worldpay_need_auth_status',
            'worldpay_prevent_capture',
        );

        foreach ($keys as $key) {
            $this->removeDataFromRegistry($key);
        }

        return $this;
    }

    public function getWpPaymentId()
    {
        return $this->getDataFromRegistry('worldpay_payment_id');
    }

    public function getWpCardNumber()
    {
        return $this->getDataFromRegistry('worldpay_card_number');
    }

    public function getWpPaymentMethod()
    {
        return $this->getDataFromRegistry('worldpay_payment_method');
    }

    public function getWpOrderId()
    {
        return $this->getDataFromRegistry('worldpay_order_id');
    }

    public function getWpRunStatus()
    {
        return $this->getDataFromRegistry('worldpay_run_status');
    }

    public function getWpNeedAuthStatus()
    {
        return $this->getDataFromRegistry('worldpay_need_auth_status');
    }

    public function getWpPreventCapture()
    {
        return $this->getDataFromRegistry('worldpay_prevent_capture');
    }

    public function setWpPaymentId($data)
    {
        return $this->addDataToRegistry('worldpay_payment_id', $data);
    }

    public function setWpCardNumber($data)
    {
        return $this->addDataToRegistry('worldpay_card_number', $data);
    }

    public function setWpPaymentMethod($data)
    {
        return $this->addDataToRegistry('worldpay_payment_method', $data);
    }

    public function setWpOrderId($data)
    {
        return $this->addDataToRegistry('worldpay_order_id', $data);
    }

    public function setWpRunStatus($data)
    {
        return $this->addDataToRegistry('worldpay_run_status', $data);
    }

    public function setWpNeedAuthStatus($data)
    {
        return $this->addDataToRegistry('worldpay_need_auth_status', $data);
    }

    public function setWpPreventCapture($data)
    {
        return $this->addDataToRegistry('worldpay_prevent_capture', $data);
    }

    public function addDataToRegistry($name, $data)
    {
        $this->removeDataFromRegistry($name);

        Mage::register($name, $data);

        return $this;

    }

    public function getDataFromRegistry($name)
    {
        if ($data = Mage::registry($name)) {
            return $data;
        }

        return false;
    }

    public function removeDataFromRegistry($name)
    {
        Mage::unregister($name);

        return $this;
    }
}
