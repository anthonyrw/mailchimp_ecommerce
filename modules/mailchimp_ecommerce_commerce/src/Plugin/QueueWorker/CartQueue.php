<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Core\Url;

/**
 * Updates Orders in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_cart_queue",
 *   title = @Translation("Mailchimp Ecommerce Cart Add/Update"),
 *   cron = {"time" = 120}
 * )
 */
class CartQueue extends QueueWorkerBase
{

  private $order_item_id;

  /**
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *    This is an array containing the order id, email, event type, and order item id
   */
  final public function processItem($data) : void
  {
    $this->order_id = $data['order_id'];
    $this->email = $data['email'];
    $this->event = $data['event'];
    $this->order_item_id = $data['order_item_id'];

    if ($this->order_item_id !== null) {
      if($this->event === 'CartOrderItemRemoveEvent') {
        $this->cartOrderItemRemove();
      }
      else if($this->event === 'CartOrderItemUpdateEvent') {
        $this->cartOrderItemUpdate();
      }
    }
    else if($this->event === 'CartEmptyEvent') {
      $this->cartEmpty();
    }
    else if($this->event === 'CartEntityAddEvent') {
      $this->cartUpdated();
    }
  }

  private function cartOrderItemRemove(): void
  {
    try {
      $this->cart_handler->deleteCartLine($this->order_id, $this->order_item_id);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to remove cart order item from mailchimp. Order ID: ' . $this->order_id);
    }
  }

  private function cartOrderItemUpdate(): void
  {
    try {
      /** @var OrderItem $order_item */
      $order_item = OrderItem::load($this->order_item_id);
      $product = $this->order_handler->buildProduct($order_item);
      $this->cart_handler->updateCartLine($this->order_id, $this->order_item_id, $product);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to update cart order item from mailchimp. Order ID: ' . $this->order_id);
    }
  }



  private function cartEmpty() : void {
    try {
      $this->cart_handler->deleteCart($this->order_id);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to delete cart from mailchimp. Order ID: ' . $this->order_id);
    }
  }
}