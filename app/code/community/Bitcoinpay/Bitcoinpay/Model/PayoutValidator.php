<?php
class Bitcoinpay_Bitcoinpay_Model_PayoutValidator extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $currency = $this->getValue(); //get the value from our config
        if(strlen($currency) != 3)   //exit if we're less than 10 digits long
        {
            Mage::throwException("BitcoinPay payment module: Bad payout currency format! Use only 3 letters");
        }
        $this->check_currency($currency);
        return parent::save();  //call original save method so whatever happened
                                //before still happens (the value saves)
    }
    private function check_currency($user_curr)
        {
            $isValid        = false;
            $settlement_url = 'https://www.bitcoinpay.com/api/v1/settlement/';
            $apiID          = $this->getFieldsetDataValue('apiKey');

            $curlheaders = array(
                "Content-type: application/json",
                "Authorization: Token {$apiID}"
            );

            $curl = curl_init($settlement_url);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curlheaders);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //bypassing ssl verification, because of bad compatibility

            $response = curl_exec($curl);

            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $jHeader     = substr($response, 0, $header_size);
            $jBody       = substr($response, $header_size);

            //http response code
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status != 200) {
                Mage::throwException("BitcoinPay payment module: API key is not VALID! Cannot connect to gate to check payout currency!");
                curl_close($curl);
            }


            $answer            = json_decode($jBody);
            $active_currencies = $answer->data->active_settlement_currencies;

            if (count($active_currencies) == 0) {
                curl_close($curl);
                Mage::throwException("BitcoinPay payment module: You must select your payout currency at BitcoinPay.com administration first!");
            }

            foreach ($active_currencies as $value) {
                if (strcmp($value, $user_curr) == 0) {
                    $isValid = true;
                    break;
                }
            }
            if (!$isValid) {
                $valid_currencies = '';
                foreach ($active_currencies as $value) {
                    $valid_currencies .= '<br/ >' . $value;
                }
                curl_close($curl);
                Mage::throwException("BitcoinPay payment module: Your Payout currency is not VALID! Select form: {$valid_currencies}");
            }


            curl_close($curl);
        }
}