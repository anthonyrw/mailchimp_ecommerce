<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\OrderItem;

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
   * Process the changes made to a cart
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *    This is an array containing the order id, email, event type, and order item id
   *
   * {@inheritDoc}
   */
  final public function processItem($data) : void
  {
    $this->order_id = $data['order_id'];
    $this->email = $data['email'];
    $this->event = $data['event'];
    $this->order_item_id = $data['order_item_id'];

    if ($this->order_item_id !== null) {
      if($this->event === 'Drupal\commerce_cart\Event\CartOrderItemRemoveEvent') {
        $this->cartOrderItemRemove();
      }
      else if($this->event === 'Drupal\commerce_cart\Event\CartOrderItemUpdateEvent') {
        $this->cartOrderItemUpdate();
      }
    }
    else if($this->event === 'Drupal\commerce_cart\Event\CartEmptyEvent') {
      $this->cartEmpty();
    }
    else if($this->event === 'Drupal\commerce_cart\Event\CartEntityAddEvent') {
      $this->cartUpdated();
    }
  }

  /**
   * Remove an order item from a cart in Mailchimp
   */
  private function cartOrderItemRemove(): void
  {
    try {
      $this->cart_handler->deleteCartLine($this->order_id, $this->order_item_id);
    } catch (\Exception $e) {
      if ($e->getCode() === 404) {
        $this->cartUpdated();
      }
      else {
        mailchimp_ecommerce_log_error_message($e->getCode() . '. Unable to remove cart order item from mailchimp. Order ID: ' . $this->order_id);
      }
    }
  }

  /**
   * Update an order item belonging to a cart in Mailchimp
   */
  private function cartOrderItemUpdate(): void
  {
    try {
      /** @var OrderItem $order_item */
      $order_item = OrderItem::load($this->order_item_id);
      $product = $this->order_handler->buildProduct($order_item);
      $this->cart_handler->updateCartLine($this->order_id, $this->order_item_id, $product);
    } catch (\Exception $e) {
      if ($e->getCode() === 404) {
        $this->cartUpdated();
      }
      else {
        mailchimp_ecommerce_log_error_message($e->getCode() . '. Unable to update cart order item from mailchimp. Order ID: ' . $this->order_id);
      }
    }
  }

  /**
   * Delete a cart in Mailchimp
   */
  private function cartEmpty() : void {
    try {
      $this->cart_handler->deleteCart($this->order_id);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to delete cart from mailchimp. Order ID: ' . $this->order_id);
    }
  }
}