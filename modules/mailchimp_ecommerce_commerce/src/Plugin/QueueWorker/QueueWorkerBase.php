<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\Core\Queue\QueueWorkerBase as DrupalQueueWorkerBase;
use Drupal\Core\Url;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Drupal\mailchimp_ecommerce\PromoHandler;

/**
 * Class MailchimpEcommerceCommerceQueueWorkerBase
 * @package Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker
 */
class QueueWorkerBase extends DrupalQueueWorkerBase {

  protected $order_id;
  protected $email;
  protected $event;

  /**
   * The Order Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\OrderHandler
   */
  protected $order_handler;

  /**
   * The Cart Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\CartHandler
   */
  protected $cart_handler;

  /**
   * The Customer Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\CustomerHandler
   */
  protected $customer_handler;

  /**
   * The Promo Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\PromoHandler
   */
  protected $promo_handler;

  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    /** @var PromoHandler promo_handler */
    $this->promo_handler = Drupal::service('mailchimp_ecommerce.promo_handler');
    /** @var CustomerHandler customer_handler */
    $this->customer_handler = Drupal::service('mailchimp_ecommerce.customer_handler');
    /** @var CartHandler cart_handler */
    $this->cart_handler = Drupal::service('mailchimp_ecommerce.cart_handler');
    /** @var OrderHandler order_handler */
    $this->order_handler = Drupal::service('mailchimp_ecommerce.order_handler');
  }

  /**
   * @inheritDoc
   */
  public function processItem($data)
  {
    // TODO: Implement processItem() method.
  }


  protected function cartUpdated(): void
  {
    $order = Order::load($this->order_id);
    $customer = [];
    $customer['email_address'] = $this->email;

    $billing_profile = $order->getBillingProfile();

    $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);

    try {
      $this->customer_handler->addOrUpdateCustomer($customer);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('unable to update a customer ' . $customer['email_address']);
    }


    // Mailchimp considers any order to be a cart until the order is complete.
    // This order is created as a cart in Mailchimp when assigned to the user.
    $order_data = $this->order_handler->buildOrder($order, $customer);

    // Add cart item price to order data.
    if (!isset($order_data['currency_code'])) {
      /** @var Price $price */
      $price = $order->getPrice();
      $order_data['currency_code'] = $price->getCurrencyCode();
      $order_data['order_total'] = $price->getNumber();
    }

    $order_data['checkout_url'] = Url::fromRoute('commerce_checkout.form', ['commerce_order' => $this->order_id], ['absolute' => TRUE])->toString();

    try {
      $this->cart_handler->addOrUpdateCart($this->order_id, $customer, $order_data);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('unable to update or add a cart in mailchimp for order ID: ' . $this->order_id);
    }
  }
}