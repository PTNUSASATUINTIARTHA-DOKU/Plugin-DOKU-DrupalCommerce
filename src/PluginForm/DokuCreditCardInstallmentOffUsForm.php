<?php

namespace Drupal\commerce_doku\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DokuCreditCardInstallmentOffUsForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_doku\Plugin\Commerce\PaymentGateway\DokuCreditCardInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();

    $callbackurl = $payment_gateway_plugin->getNotifyUrl()->toString();
    $responseurl = Url::FromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order_id,
      'step' => 'payment'
    ], ['absolute' => TRUE])->toString();

      $f_data = [
      'merchant_id' => $callbackurl,
      'order_id' => $responseurl
    ];

    $data = $payment_gateway_plugin->buildTransaction($payment);
    $redirect_url = $payment_gateway_plugin->getPaymentRedirect();
    
    return $this->buildRedirectForm($form, $form_state, $redirect_url, $data, 'post');
  }


}