<?php

class Bitcoinpay_Bitcoinpay_IpnController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var Mage_Sales_Model_Order
     */
    private $order;

    /**
     * Bitcoinpay IPN front action
     */
    public function indexAction()
    {
      //Mage::log('Callback called!', null, 'bcp-callback.log', true);

      $callback = Mage::getStoreConfig('payment/Bitcoinpay/callback');

      $inputData = file_get_contents('php://input');
      $payResponse = json_decode($inputData);

      /**  CALLBACK PASSWORD CHECK */
        if (!is_null($callback)){
            $paymentHeaders = getallheaders();
            $digest =  $paymentHeaders["Bpsignature"];

            $hashMsg = $inputData . $callback;
            $checkDigest = hash('sha256', $hashMsg);

            if (strcmp($digest, $checkDigest) !== 0){
                Mage::log('Bitcoinpay - Invalid signature for callback');
                Mage::app()->getResponse()
                ->setHeader('HTTP/1.1', '400 Bad Request')
                ->sendResponse();
                exit('Invalid Signature');
            }
        }

        //payment status
        $paymentStatus = $payResponse -> status;


        //get orderID from msg
        $preOrderId = json_decode($payResponse -> reference);
        $orderId =  $preOrderId -> order_number;

        //load order
        $this->order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        Mage::log('Callback called!', null, 'bcp-callback.log', true);
        Mage::log("Calling callback w status: {$paymentStatus}", null, 'bcp-callback.log', true);
        //process response
        switch($paymentStatus) {
					case 'confirmed':
            $this->order->addStatusHistoryComment('Order marked as confirmed. Bitcoinpay', false);
            $this->pay($payResponse->payment_id,$payResponse->price);
            $this->order->sendOrderUpdateEmail(true, 'Order marked as confirmed. Bitcoinpay');
						break;
					case 'pending':
            $this->order->addStatusHistoryComment('Order marked as pending. Bitcoinpay', false);
            $this->setPending();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as pending. Bitcoinpay');
						break;
          case 'received':
            $this->order->addStatusHistoryComment('Order marked as received. Bitcoinpay', false);
            $this->setPending();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as recieved. Bitcoinpay');
						break;
          case 'insufficient_amount':
            $this->order->addStatusHistoryComment('Order marked as insufficient amount. Bitcoinpay', false);
            $this->setCancel();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as insufficient amount. Bitcoinpay');
						break;
          case 'invalid':
            $this->order->addStatusHistoryComment('Order marked as invalid. Bitcoinpay', false);
            $this->setCancel();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as invalid. Bitcoinpay');
						break;
          case 'timeout':
            $this->order->addStatusHistoryComment('Order marked as timeout. Bitcoinpay', false);
            $this->setCancel();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as timeout. Bitcoinpay');
						break;
          case 'refund':
            $this->order->addStatusHistoryComment('Order marked as REFUND. Bitcoinpay', false);
            $this->setRefund();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as REFUND. Bitcoinpay');
						break;
          case 'paid_after_timeout':
            $this->order->addStatusHistoryComment('Order marked as paid after timeout. Bitcoinpay', false);
            $this->setCancel();
            $this->order->sendOrderUpdateEmail(true, 'Order marked as paid after timeout. Bitcoinpay');
						break;

				}
    }
    public function bcpReturn(){
      $this->_redirectSuccess($defaultUrl);
    }
    /**
     * Transaction PAID type IPN
     * @param $ref reference id
     * @param $amount amount of money
     */
    private function pay($ref, $amount)
    {
        $payment = $this->order->getPayment();
        $payment->setTransactionId($ref);
        //$payment->setPreparedMessage('Bitcoinpay: Paid with merchantReference:' . $ref);
        $payment->setShouldCloseParentTransaction(true);
        $payment->setIsTransactionCLosed(0);
        $payment->registerCaptureNotification($amount);

        $this->order->save();
    }

    private function setPending(){
        $this->order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'Pending.');
        $this->order->setStatus("Pending");
        $this->order->save();
    }

    private function setCancel(){
        if(!$this->order->isPaymentReview() || $this->order->hasInvoices()) {
        } else {
        $this->order->registerCancellation("Order was cancelled")->save();
      }

    }

    private function setRefund(){
      if(!$this->order->isPaymentReview() || $this->order->hasInvoices()) {
        } else {
        $this->order->registerCancellation("Order need to be refunded")->save();
    }
    }
}