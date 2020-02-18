<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;

/**
 * Updates Orders in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_order_paid_queue",
 *   title = @Translation("Mailchimp Ecommerce Order Paid Queue"),
 *   cron = {"time" = 120}
 * )
 */
class OrderPaidQueue extends QueueWorkerBase {

  /**
   * Fires once when order balance reaches zero.
   */
  public function processItem($data)
  {
    $order_data['id'] = $data->id();
    $order_data['financial_status'] = 'paid';
    $this->order_handler->updateOrder($data->id(), $order_data);
  }
}