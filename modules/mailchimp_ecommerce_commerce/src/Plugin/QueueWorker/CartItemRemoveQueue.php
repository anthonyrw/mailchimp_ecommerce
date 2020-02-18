<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_cart_item_remove_queue",
 *   title = @Translation("Mailchimp Ecommerce Cart Add Queue"),
 *   cron = {"time" = 120}
 * )
 */
class CartItemRemoveQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $data->getCart();

    if (empty($order->getItems())) {
      $this->cart_handler->deleteCart($order->id());
    }
    else {
      $this->cart_handler->deleteCartLine($order->id(), $data->getOrderItem()->id());
    }
  }
}