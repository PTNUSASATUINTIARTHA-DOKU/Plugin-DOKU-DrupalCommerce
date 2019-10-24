<?php

namespace Drupal\commerce_doku\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;

/**
 * Provides the Doku Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "doku_creditcard",
 *   label = "Doku Credit Card",
 *   display_label = "Online Payment via Doku Credit Card",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_doku\PluginForm\DokuCreditCardForm",
 *   },
*   modes= {
 *     "staging" = "Staging",
 *     "production" = "Production"
 *   },
 * )
 */
class DokuCreditCard extends OffsitePaymentGatewayBase implements DokuCreditCardInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
        global $base_url;
    return [
        'mallid' => '',
        'chainid' => '',
        'sharedkey' => '',
        'service_edu' => '',
        'tokenization' => '',
        'redirect' => $base_url.'/dc/payment_doku/redirect',
        'notify' => $base_url.'/dc/payment_doku/notify',
        'identify' => $base_url.'/dc/payment_doku/identify',
        'review' => $base_url.'/dc/payment_doku/review',
        ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['mallid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mall ID'),
      '#description' => $this->t('Input your Doku Mall ID (e.g M012345). Get the ID <a href="https://bo.doku.com/v2/?continue=https://merchant.doku.com/acc/" target="_blank">here</a>'),
      '#default_value' => $this->configuration['mallid'],
      '#required' => TRUE,
    ];

    $form['chainid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chain ID'),
      '#description' => $this->t('Input your Doku Chain ID. Get the key <a href="https://bo.doku.com/v2/?continue=https://merchant.doku.com/acc/" >here</a>'),
      '#default_value' => $this->configuration['chainid'],
      '#required' => TRUE,
    ];

    $form['sharedkey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shared Key'),
      '#description' => $this->t('Input your Doku Shared key. Get the key <a href="https://bo.doku.com/v2/?continue=https://merchant.doku.com/acc/" >here</a>'),
      '#default_value' => $this->configuration['sharedkey'],
      '#required' => TRUE,
    ];

    $form['service_edu'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Service Edu'),
      '#description' => $this->t('You must enable Service EDU. Please contact us if you wish to enable this feature.'),
      '#default_value' => $this->configuration['service_edu'],
    ];

    $form['tokenization'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Service tokenization'),
      '#default_value' => $this->configuration['tokenization'],
      '#description' => $this->t('You must enable service tokenization, please contact us if you wish to enable this feature'),
    ];

    $form['redirect'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Redirect URL'),
      '#description' => $this->configuration['redirect'],
      '#default_value' => $this->configuration['redirect'],
      // '#required' => TRUE,
    ];

    $form['notify'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notify URL'),
      '#description' => $this->configuration['notify'],
      '#default_value' => $this->configuration['redirect'],
      // '#required' => TRUE,
    ];

    $form['identify'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Identify URL'),
      '#description' => $this->configuration['identify'],
      '#default_value' => $this->configuration['redirect'],
      // '#required' => TRUE,
    ];

    $form['review'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Review URL'),
      '#description' => $this->configuration['review'],
      '#default_value' => $this->configuration['redirect'],
      // '#required' => TRUE,
    ];

    return $form; 
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {    
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['mallid'] = $values['mallid'];
      $this->configuration['chainid'] = $values['chainid'];
      $this->configuration['sharedkey'] = $values['sharedkey'];
      $this->configuration['service_edu'] = $values['service_edu'];
      $this->configuration['tokenization'] = $values['tokenization'];
    }
  }

    /**
     * {@inheritdoc}
     */
    protected function loadPaymentByOrderId($order_id) {
        /** @var \Drupal\commerce_payment\PaymentStorage $storage */
        $storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment_by_order_id = $storage->loadByProperties(['remote_id' => $order_id]);
        return reset($payment_by_order_id);
    }

  /**
   * Return correct payment API endpoint
   *
   * @return string
   */
  public function getPaymentRedirect() {
    $config = $this->getConfiguration();
    $dokuredirect = 'https://pay.doku.com/Suite/Receive';
    if ($config['mode'] == 'staging') {
      $dokuredirect = 'https://staging.doku.com/Suite/Receive';
    }
    return $dokuredirect;
  }

    /**
     * {@inheritdoc}
     */
    public function getData() {
          $configuration = $this->getConfiguration();
          if ($configuration['service_edu']){
            return '1';
          }
          return '0';
      // return $configuration['service_edu'];
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

    /**
     * {@inheritdoc}
     */
    public function getipaddress()    
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])){
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

  /**
   * {@inheritdoc}
   */
    public function buildTransaction(PaymentInterface $payment) {
    global $base_url;
    $configuration = $this->getConfiguration();
    $order = $payment->getOrder();
    $email = $order->getEmail();
    $payment_channel = '15';

    if($configuration['tokenization']){
      $payment_channel='16';
    }

    // $order = Order::load($order_id);
    $address = $order->getBillingProfile()->address->first();
    // Build data for transaction.
    // Requires value in minor units.
    $paymentAmount = number_format($payment->getAmount()->getNumber(), 2, '.', '');
    $reqdatetime = date("YmdHis");
    $sessionValidity = date("c",strtotime("+1 days"));
    $words = $paymentAmount.$configuration['mallid'].$configuration['sharedkey'].$order->id();
    $word = sha1($words);
    $session = md5($words);
    $basket = 'Transaction Transidmerchant '.$order->id().','.$paymentAmount.',1,'.$paymentAmount.';';

    $params = [
      'MALLID'            => $configuration['mallid'],
      'CHAINMERCHANT'     => $configuration['chainid'],
      'CURRENCY'          => '360',
      'PURCHASECURRENCY'  => '360',
      'AMOUNT'            => $paymentAmount,
      'PURCHASEAMOUNT'    => $paymentAmount,
      'TRANSIDMERCHANT'   => $order->id(),
      'WORDS'             => $word,
      'REQUESTDATETIME'   => $reqdatetime,
      'SESSIONID'         => $session,
      'PAYMENTCHANNEL'    => $payment_channel,
      'EMAIL'             => $email,
      'NAME'              => $address->getGivenName().' '.$address->getFamilyName(),
      'ADDRESS'           => $address->getAddressLine1().' '.$address->getAddressLine2(),
      'COUNTRY'           => $address->getCountryCode(),
      // 'HOMEPHONE'         => $address->getFamilyName(),
      // 'WORKPHONE'         => $address->getFamilyName(),
      'MOBILEPHONE'       => '',
      'BASKET'            => $basket,
      'CITY'              => $address->getLocality(),
      'STATE'             => $address->getAdministrativeArea(),
      'ZIPCODE'           => $address->getPostalCode(),
      'CUSTOMERID'        => $email
    ];

    $trx['ip_address']                    = $this->getipaddress();
    $trx['process_datetime']              = date("Y-m-d H:i:s");
    $trx['process_type']                  = 'REQUEST';
    $trx['transidmerchant']               = $order->id();
    $trx['amount']                        = $paymentAmount;
    $trx['words']                         = $word;
    $trx['session_id']                    = $session;
    $trx['payment_channel']               = $payment_channel;
    $trx['message']                       = "Transaction request start";

    $this->add_doku($trx);
    $payment->setRemoteId('Request Payment');
    $payment->setState('processing');
    $payment->save();
    return $params;
  }

}
?>