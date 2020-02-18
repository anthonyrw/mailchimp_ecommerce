<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_cart_item_add_queue",
 *   title = @Translation("Mailchimp Ecommerce Cart Add Queue"),
 *   cron = {"time" = 120}
 * )
 */
class CartItemAddQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $data->getCart();
    /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
    $order_item = $data->getOrderItem();

    $product = $this->order_handler->buildProduct($order_item);

    $this->cart_handler->updateCartLine($order->id(), $order_item->id(), $product);
  }
}