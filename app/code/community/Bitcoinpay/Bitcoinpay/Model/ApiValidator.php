<?php
class Bitcoinpay_Bitcoinpay_Model_ApiValidator extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $api = $this->getValue(); //get the value from our config
        if(strlen($api) != 24)   //exit if we're less than 10 digits long
        {
            Mage::throwException("BitcoinPay payment module: Your API key is not VALID!");
        }

        return parent::save();  //call original save method so whatever happened
                                //before still happens (the value saves)
    }
}