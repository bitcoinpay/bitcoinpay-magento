<?php

class Bitcoinpay_Bitcoinpay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'Bitcoinpay';

    protected $_isGateway = true;

    protected $_canAuthorize = true;

    protected $_canCapture = false;

    protected $_canCapturePartial = true;

    protected $_canRefundInvoicePartial = true;

    protected $_canRefund = true;

    protected $_canVoid = false;

    protected $_canUseInternal = true;

    protected $_canUseCheckout = true;

    protected $_canUseForMultishipping = true;

    protected $_canSaveCc = false;

    /**
     * Get URL to redirect to
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        Mage::Log('returning redirect url:: ' . Mage::getSingleton('customer/session')->getRedirectUrl() );
        return Mage::getSingleton('customer/session')->getRedirectUrl();
    }

    /**
     * Magento Authorize
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this|Mage_Payment_Model_Abstract
     * @throws Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $apiKey = Mage::getStoreConfig('payment/Bitcoinpay/apiKey');
        $payout = Mage::getStoreConfig('payment/Bitcoinpay/payout');
        $notiEmail = Mage::getStoreConfig('payment/Bitcoinpay/email');
        $callback = Mage::getStoreConfig('payment/Bitcoinpay/callback');

        if (is_null($apiKey)) {
            throw new Exception('To Admin: Missing API Key');
        }
        if (is_null($payout)) {
            throw new Exception('To Admin: Missing Payout currency');
        }
        //TODO payout currency check

        /** @var \Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        //additional customer data
        $customData = array(
            'customer_name' => $order->getCustomerName(),
            'order_number' => $order->getIncrementId(),
            'customer_email' => $order->getCustomerEmail()
        );
        $jCustomData = json_encode($customData);

        //data packing
        //additional checks
        $locale = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        Mage::log('Locale is: ' . $locale, null, 'bcp.log', true);
        $postData = array(
            'settled_currency' => $payout,
            'return_url' => Mage::getUrl('bitcoinpay_callback/return'),
            'notify_url' => Mage::getUrl('bitcoinpay_callback/ipn'),
            'price' => floatval($amount),
            'currency' => $order->getBaseCurrencyCode(),
            'reference' => json_decode($jCustomData)
        );

        if (($notiEmail !== NULL) && (strlen($notiEmail) > 5)){
            $postData['notify_email'] = $notiEmail;
            }
        if ((strcmp($locale, "cs") !== 0)&&(strcmp($locale, "en") !== 0)&&(strcmp($locale, "de") !== 0)){
            $postData['lang'] = "en";
        }
        else{
            $postData['lang'] = $locale;
        }

        $content = json_encode($postData);

        $cheaders = array(
            "Content-type: application/json",
            "Authorization: Token {$apiKey}"
        );


        $url = 'https://www.bitcoinpay.com/api/v1/payment/btc';

        $curlHandler = curl_init($url);
        curl_setopt($curlHandler, CURLOPT_HEADER, true);
        curl_setopt($curlHandler, CURLOPT_VERBOSE, true);
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandler, CURLOPT_HTTPHEADER,$cheaders);
        curl_setopt($curlHandler, CURLOPT_POST, true);
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, false); //bypassing ssl verification, because of bad compatibility
        curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $content);

        //sending to server, and waiting for response
        $response = curl_exec($curlHandler);

        $header_size = curl_getinfo($curlHandler, CURLINFO_HEADER_SIZE);
        $jHeader = substr($response, 0, $header_size);
        $jBody = substr($response, $header_size);

        $jHeaderArr = $this -> get_headers_from_curl_response($jHeader);

        //http response code
        $status = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);

        /**  CALLBACK CHECK */
        $security = 1;
        if (!is_null($callback)){
            $digest =  $jHeaderArr[0]["BPSignature"];
            $hashMsg = $jBody . $callback;
            $checkDigest = hash('sha256', $hashMsg);

            if (strcmp($digest, $checkDigest) == 0){
              $security = 1;
            }
            else{
              $security = 0;
            }
        }

        if ( $status != 200 ) {
            curl_close($curlHandler);
            throw new Exception('To Admin: General error not 200');
        }
        elseif(!$security){
          curl_close($curlHandler);
          throw new Exception('To Admin: Callback password does not match!');
        }
        curl_close($curlHandler);

        $response = json_decode($jBody);

        $paymentUrl = $response->data->payment_url;
        /* set order status to `Payment Review` */

        $payment->setIsTransactionPending(true);
        $payment->setTransactionId($response->data->payment_id);
        $order->addStatusHistoryComment("BitcoinPay Invoice: https://bitcoinpay.com/cs/sci/invoice/btc/{$response->data->payment_id}", false);
        Mage::getSingleton('customer/session')->setRedirectUrl($paymentUrl);
        return $this;
    }

    private function get_headers_from_curl_response($headerContent){
        $headers = array();

        // Split the string on every "double" new line.
        $arrRequests = explode("\r\n\r\n", $headerContent);

        // Loop of response headers. The "count() -1" is to
        //avoid an empty row for the extra line break before the body of the response.
        for ($index = 0; $index < count($arrRequests) -1; $index++) {

            foreach (explode("\r\n", $arrRequests[$index]) as $i => $line)
            {
                if ($i === 0)
                    $headers[$index]['http_code'] = $line;
                else
                {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$index][$key] = $value;
                }
            }
        }

        return $headers;
    }
}