<?php

namespace Drupal\commerce_doku\Controller;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\Core\Access\AccessException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Psr\Log\LoggerInterface;
use Drupal\commerce_payment\Controller;

/**
 * Provides the endpoint for payment notifications.
 */
class DokuNotification {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Provides the "notify" page.
   *
   * Also called the "IPN", "status", "webhook" page by payment providers.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */


function commerce_doku_identify(Request $request)
{
      $order_id = $_REQUEST['TRANSIDMERCHANT'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal\commerce_order\Entity\Order::load($order_id);
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getPaymentInterface($order);

            // if ( empty($_POST) )
            if ( empty($_REQUEST) )
        {
            echo "Stop : Access Not Valid";
            die;
        }

    $payment_gateway_plugin = $this->getPaymentGateway($order_id);
    $getIp = $payment_gateway_plugin->getipaddress();

    $trx['ip_address']          = $payment_gateway_plugin->getipaddress();
    $trx['payment_code']        = $_REQUEST['PAYMENTCODE'];
    $trx['amount']              = $_REQUEST['AMOUNT'];
    $trx['transidmerchant']     = $order_id; 
    $trx['payment_channel']     = $_REQUEST['PAYMENTCHANNEL'];
    $trx['session_id']          = $_REQUEST['SESSIONID'];
    $trx['process_datetime']    = date("Y-m-d H:i:s");
    $trx['process_type']        = 'IDENTIFY';
    $trx['ip_address']          = $payment_gateway_plugin->getipaddress();
    if ($trx['payment_code']){
    $trx['message']             = "Identify process message come from DOKU and payment code : ".$trx['payment_code'];
    $status = 'waiting';
    $process_plugin = 'Identify';
    $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
    } else {
    $trx['message']             = "Identify process message come from DOKU";
    }
    $result = $this->checkTrx($trx,"","");   
    if ( $result < 1 )
            {
                echo "Stop : Transaction Not Found";
                die;            
            }

    $this->add_doku($trx);
    echo 'Continue';
    die;
}

function commerce_doku_notify(Request $request)
{
    $order_id = $_REQUEST['TRANSIDMERCHANT'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal\commerce_order\Entity\Order::load($order_id);
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getPaymentInterface($order);
            // if ( empty($_POST) )
          if ( empty($_REQUEST) )
        {
            echo "Stop : Access Not Valid";
            die;
        }
 
      $payment_gateway_plugin = $this->getPaymentGateway($order_id);
      $getIp = $payment_gateway_plugin->getipaddress();

      $trx['words']                     = $_REQUEST['WORDS'];
      $trx['amount']                    = $_REQUEST['AMOUNT'];
      $trx['transidmerchant']           = $_REQUEST['TRANSIDMERCHANT'];
      $trx['result_msg']                = $_REQUEST['RESULTMSG'];            
      $trx['verify_status']             = $_REQUEST['VERIFYSTATUS'];
      $trx['ip_address']                = $getIp;
      $trx['response_code']             = $_REQUEST['RESPONSECODE'];
      $trx['approval_code']             = $_REQUEST['APPROVALCODE'];
      $trx['payment_channel']           = $_REQUEST['PAYMENTCHANNEL'];
      $trx['payment_code']              = $_REQUEST['PAYMENTCODE'];
      $trx['session_id']                = $_REQUEST['SESSIONID'];
      $trx['bank_issuer']               = $_REQUEST['BANK'];
      $trx['creditcard']                = $_REQUEST['MCN'];                   
      $trx['doku_payment_datetime']     = $_REQUEST['PAYMENTDATETIME'];
      $trx['process_datetime']          = date("Y-m-d H:i:s");
      $trx['verify_id']                 = $_REQUEST['VERIFYID'];
      $trx['verify_score']              = $_REQUEST['VERIFYSCORE'];
      $trx['notify_type']               = $_REQUEST['STATUSTYPE'];

      switch ( $trx['notify_type'] )
      {
          case "P":
          $trx['process_type'] = 'NOTIFY';
          break;
      
          case "V":
          $trx['process_type'] = 'REVERSAL';
          break;
      } 
      $result = $this->checkTrx($trx,"","");

            if ( $result < 1 )
      {
          echo "Stop : Transaction Not Found";
          die;
          } else {
          $use_edu = $payment_gateway_plugin->getData();
              $process_plugin = 'Notify';
                    switch (TRUE)
          {
              case ( $trx['result_msg']=="SUCCESS" && $trx['notify_type']=="P" && in_array($trx['payment_channel'], array("05","14","22","29","31","32","33","34","35","36","40","41","42","43","44")) ):
              $trx['message'] = "Notify process message come from DOKU. Payment Success : Completed";
              $status = 'success';
              $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
              break;

              case ( $trx['result_msg']=="SUCCESS" && $trx['notify_type']=="P" && $use_edu == 1 ):
              $trx['message'] = "Notify process message come from DOKU. Payment success but wait for EDU verification : Processed";
              $status = 'pending';
              $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
              break;

              case ( $trx['result_msg']=="SUCCESS" && $trx['notify_type']=="P" && $use_edu == 0 ):
              $trx['message'] = "Notify process message come from DOKU. Payment Success : Completed";
              $status = 'success';
              $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
              break;

              case ( $trx['notify_type']=="V" ):
              $trx['message'] = "Notify process message come from DOKU. Payment Void by EDU : Denied";
              $status = 'failed';
              $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
              break; 

              default:
              $trx['message'] = "Notify process message come from DOKU. Payment Failed by default : Cancelled";
              $status = 'failed';
              $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
              break;

          }
          $this->add_doku($trx);
                    
          echo "Continue";
            die;
          }
}

function commerce_doku_redirect()
{
    $requestTime = \Drupal::time()->getRequestTime();

    $order_id = $_REQUEST['TRANSIDMERCHANT'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal\commerce_order\Entity\Order::load($order_id);
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getPaymentInterface($order);

    $payment_gateway_plugin = $this->getPaymentGateway($order_id);
    $getIp = $payment_gateway_plugin->getipaddress();

    $trx['words']                = $_REQUEST['WORDS'];
    $trx['amount']               = $_REQUEST['AMOUNT'];
    $trx['transidmerchant']      = $_REQUEST['TRANSIDMERCHANT']; 
    $trx['status_code']          = $_REQUEST['STATUSCODE'];
    $trx['payment_code']         = $_REQUEST['PAYMENTCODE'];
    $trx['payment_channel']      = $_REQUEST['PAYMENTCHANNEL'];
    $trx['session_id']           = $_REQUEST['SESSIONID'];

    $trx['ip_address']       = $getIp;
    $trx['process_datetime'] = date("Y-m-d H:i:s");
    $trx['process_type']     = 'REDIRECT';

    $use_edu = $payment_gateway_plugin->getData();
    $url_redirect = Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order_id,
      'step' => 'payment',
    ], ['absolute' => TRUE])->toString();

      if ( in_array($trx['payment_channel'], array("05","14","22","29","31","32","33","34","35","36","40","41","42","43","44")) && $trx['status_code'] == "5511" ){
        $trx['message'] = "Redirect process come from DOKU. Payment channel using ATM Transfer / Convenience Store, transaction is pending for payment";  
        $status         = 'waiting';                  
        $data['return_message'] = "This is your Payment Code : ".$trx['payment_code']."<br>Please do the payment before expired.<br>If you need help for payment, please contact our customer service.<br>";
        $url_redirect = Url::fromRoute('user.page')->toString();
        }  else {

        $result = $this->checkTrx($trx, "NOTIFY", "SUCCESS");

        if ( $result < 1 ){
          $trx['message'] = "Redirect process with no notify message come from DOKU. Transaction is Failed. Please check on Back Office."; 
          $status         = 'failed';       
          $data['return_message'] = "Your Transaction is Failed<br>Your payment is failed. Please check your payment detail or please try again later.";
        $url_redirect = Url::fromRoute('user.page')->toString();
        } else {
          if ( intval($use_edu) == 1 && $trx['payment_channel']== "15")
          {         
            $trx['message'] = "Redirect process with no notify message come from DOKU. Transaction is Success, wait for EDU Verification. Please check on Back Office.";  
            $status         = 'pending';                  
            $data['return_message'] = "Your Transaction is Waiting for Payment Verification<br />Please Wait While We Verified Your Payment<br />Thank you for shopping with us. We will process your payment soon.";
            $url_redirect = Url::fromRoute('user.page')->toString(); 
          }
          else
          {
              $trx['message'] = $result."Redirect process with no notify message come from DOKU. Transaction is Success. Please check on Back Office.";  
              $status         = 'success';        
              $data['return_message'] = "Your Transaction is Success<br />Your payment is success. We will process your order. Thank you for shopping with us.";
          }       
        }
    $this->add_doku($trx);
    $process_plugin = 'Redirect';
    $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
    $messenger->addMessage(t($data['return_message']));
    return new RedirectResponse($url_redirect);
}
}

function commerce_doku_review()
{
      $order_id = $_REQUEST['TRANSIDMERCHANT'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal\commerce_order\Entity\Order::load($order_id);
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getPaymentInterface($order);
              // if ( empty($_POST) )
          if ( empty($_REQUEST) )
        {
            echo "Stop : Access Not Valid";
            die;
        }
      $order_id = $_REQUEST['TRANSIDMERCHANT'];
      $payment_gateway_plugin = $this->getPaymentGateway($order_id);
      $getIp = $payment_gateway_plugin->getipaddress();
      $use_edu = $payment_gateway_plugin->getData();

      if ($use_edu=='0'){
        echo "Stop : Request Not Valid";
        die;
      }

                $trx['amount']                  = $_REQUEST['AMOUNT'];
                $trx['transidmerchant']         = $_REQUEST['TRANSIDMERCHANT'];
                $trx['result_msg']              = $_REQUEST['RESULTMSG'];            
                $trx['verify_status']           = $_REQUEST['VERIFYSTATUS'];        
                $trx['words']                   = $_REQUEST['WORDS'];
                $trx['process_datetime']        = date("Y-m-d H:i:s");
                $trx['process_type']            = 'REVIEW';
                $trx['ip_address']              = $this->getipaddress();
                $trx['notify_type']             = $_REQUEST['STATUSTYPE'];                
                $trx['response_code']           = $_REQUEST['RESPONSECODE'];
                $trx['approval_code']           = $_REQUEST['APPROVALCODE'];
                $trx['payment_channel']         = $_REQUEST['PAYMENTCHANNEL'];
                $trx['payment_code']            = $_REQUEST['PAYMENTCODE'];
                $trx['session_id']              = $_REQUEST['SESSIONID'];
                $trx['bank_issuer']             = $_REQUEST['BANK'];
                $trx['creditcard']              = $_REQUEST['MCN'];                   
                $trx['doku_payment_datetime']   = $_REQUEST['PAYMENTDATETIME'];
                $trx['verify_id']               = $_REQUEST['VERIFYID'];
                $trx['verify_score']            = $_REQUEST['VERIFYSCORE'];

            $result = $this->checkTrx($trx,"","");
                    
            if ( $result < 1 )
            {
                        echo "Stop : Transaction Not Found";
                        die;            
            }
                        $process_plugin='Review';
                        switch (TRUE)
                        {
                            case ( $trx['verify_status']=="APPROVE" ):
                            $status = 'success';
                            $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
                            break;
                            
                            case ( $trx['verify_status']=="REVIEW" ):
                            $status = 'success';
                            $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
                            break;
                            
                            case ( $trx['verify_status']=="REJECT" || $trx['verify_status']=="HIGHRISK" || $trx['verify_status']=="NA" ):
                            $status = 'reject';
                            $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
                            break;
                            
                            default:
                            $status = 'pending';
                            $orderTrans = $this->setStatus($payment,$order,$status,$process_plugin);
                            break;
                        }
                        $this->add_doku($trx);
                        echo "Continue";

}

function getPaymentGateway($orderId){
    $order = \Drupal\commerce_order\Entity\Order::load($orderId);
    $payment_gateway = $order->get('payment_gateway')->first()->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    if (!$payment_gateway_plugin instanceof SupportsNotificationsInterface) {
      throw new AccessException('Invalid payment gateway provided.');
    }

    return $payment_gateway_plugin;

}

  /**
   * {@inheritdoc}
   */
  public function add_doku($datainsert) 
{
  global $databases;
  
  $db_prefix = $databases['default']['default']['prefix'];

    $SQL = "";
    
    foreach ( $datainsert as $field_name=>$field_data )
    {
        $SQL .= " $field_name = '$field_data',";
    }
    $SQL = substr( $SQL, 0, -1 );

    db_query("INSERT INTO ".$db_prefix."doku SET $SQL");
}

  public function checkTrx($trx, $process, $result_msg)
    {
      if ($process == ""){
        $process='REQUEST';
      }
  global $databases;
    $db_prefix = $databases['default']['default']['prefix'];

        if ( $result_msg == "PENDING" ) return 0;
        
        $check_result_msg = "";
        if ( !empty($result_msg) )
        {
          $check_result_msg = " AND result_msg = '$result_msg'";
        }   
    
        $query = db_query("SELECT * FROM " . $db_prefix . "doku" .
                                  " WHERE process_type = '$process'" .
                                  $check_result_msg.
                                  " AND transidmerchant = '" . $trx['transidmerchant'] . "'" .
                                  " AND amount = '". $trx['amount'] . "'".
                                  " AND session_id = '". $trx['session_id'] . "'" );        
        $row = $query->fetchAll();
        return count($row);
      }

    /**
     * {@inheritdoc}
     */
    protected function setStatus(PaymentInterface $paymentinterface, OrderInterface $order, $status, $process_plugin) {
    /** @var \Drupal\commerce_payment\PaymentStorage $payment_storage */
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $requestTime = \Drupal::time()->getRequestTime();

    if ($status=='waiting'){
    $data = [
      'state' => 'waiting',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $paymentinterface->getPaymentGateway(),
      'order_id' => $order->id(),
      'remote_id' => $process_plugin,
      'remote_state' => 'waiting',
    ];

    $payment = $payment_storage->create($data);
    $payment->save();

    $order->set('state', 'waiting');
    $order->set('order_number', $order->id()); 
    $order->save();

    }else if ($status=='pending'){
    $data = [
      'state' => 'pending',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $paymentinterface->getPaymentGateway(),
      'order_id' => $order->id(),
      'remote_id' => $process_plugin,
      'remote_state' => 'pending',
    ];

    $payment = $payment_storage->create($data);
    $payment->save();

    $order->set('state', 'pending');
    $order->set('order_number', $order->id()); 
    // $order->set('placed',$requestTimes);
    $order->save();

    }else if ($status=='success'){
    $data = [
      'state' => 'completed',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $paymentinterface->getPaymentGateway(),
      'order_id' => $order->id(),
      'remote_id' => $process_plugin,
      'remote_state' => 'complated',
      'authorized' => $requestTime,
      'completed' => $requestTime,
    ];

    $payment = $payment_storage->create($data);
    $payment->save();

    $order->set('state', 'completed');
    $order->set('order_number', $order->id()); 
    $order->set('placed',$requestTime);
    $order->set('completed',$requestTime);
    $order->set('cart','0');
    // $order->set('placed',$requestTimes);
    $order->save();

    }else if ($status=='reject'){
    $data = [
      'state' => 'reject',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $paymentinterface->getPaymentGateway(),
      'order_id' => $order->id(),
      'remote_id' => $process_plugin,
      'remote_state' => 'reject',
    ];

    $payment = $payment_storage->create($data);
    $payment->save();

    $order->set('state', 'reject');
    $order->set('order_number', $order->id()); 
    // $order->set('placed',$requestTimes);
    $order->save();

    }else {
    $data = [
      'state' => 'failed',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $paymentinterface->getPaymentGateway(),
      'order_id' => $order->id(),
      'remote_id' => $process_plugin,
      'remote_state' => 'failed',
    ];

    $payment = $payment_storage->create($data);
    $payment->save();

    $order->set('state', 'failed');
    $order->set('order_number', $order->id()); 
    $order->save();

    }
      return $order;
      }    

    /**
     * {@inheritdoc}
     */
    protected function getPaymentInterface (OrderInterface $order){
      /** @var \Drupal\commerce_payment\PaymentStorage $payment_storage */
      $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
      /** @var \Drupal\commerce_payment\Entity\Payment[] $payment */
      $order_payments = $payment_storage->loadMultipleByOrder($order);
      /** @var \Drupal\commerce_payment\Entity\Payment $last_payment */
      $last_payment = end($order_payments);
      return $last_payment;
    }

}

?>