<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_price\Price;
use Drupal\Core\Url;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_cart_add_queue",
 *   title = @Translation("Mailchimp Ecommerce Cart Add Queue"),
 *   cron = {"time" = 120}
 * )
 */
class CartAddQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $data->getCart();

    $customer['email_address'] = $order->getEmail();

    if (empty($customer['email_address'])) {
      // Cannot create or add an item to a cart with no customer email address.
      return;
    }

    if ($this->cart_handler->cartExists($order->id())) {
      // Add item to the existing cart.
      /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
      $order_item = $data->getOrderItem();

      $product = $this->order_handler->buildProduct($order_item);

      $this->cart_handler->updateCartLine($order->id(), $order_item->id(), $product);
    }
    else {
      // Create a new cart.
      $billing_profile = $order->getBillingProfile();
      $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);

      // Update or add customer in case this is a new cart.
      $this->customer_handler->addOrUpdateCustomer($customer);

      $order_data = $this->order_handler->buildOrder($order, $customer);

      // Add cart total price to order data.
      if (!isset($order_data['currency_code'])) {
        /** @var Price $price */
        $price = $data->getEntity()->getPrice();

        $order_data['currency_code'] = $price->getCurrencyCode();
        $order_data['order_total'] = $price->getNumber();
      }

      $order_data['checkout_url'] = Url::fromRoute('commerce_checkout.form', ['commerce_order' => $order->id()], ['absolute' => TRUE])->toString();
      $this->cart_handler->addOrUpdateCart($order->id(), $customer, $order_data);
    }
  }
}