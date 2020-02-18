<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal;
use Drupal\Core\Queue\QueueWorkerBase as DrupalQueueWorkerBase;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Drupal\mailchimp_ecommerce\PromoHandler;

/**
 * Class MailchimpEcommerceCommerceQueueWorkerBase
 * @package Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker
 */
class QueueWorkerBase extends DrupalQueueWorkerBase {

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
}