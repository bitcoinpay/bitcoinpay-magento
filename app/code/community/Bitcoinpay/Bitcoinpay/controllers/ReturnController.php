<?php

class Bitcoinpay_Bitcoinpay_ReturnController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {

      $returnStatus = $_GET['bitcoinpay-status'];

      Mage::log('Return called! status: ' . $returnStatus, null, 'bcp-callback.log', true);

      if(strcmp($returnStatus,"true") == 0)
        $this->_redirect('checkout/onepage/success');
      elseif(strcmp($returnStatus,"received") == 0)
        $this->_redirect('checkout/onepage/success');
      elseif(strcmp($returnStatus,"cancel") == 0)
        $this->_redirect('checkout/onepage/failure');
      else
        $this->_redirect('checkout/onepage/failure');
    }
}