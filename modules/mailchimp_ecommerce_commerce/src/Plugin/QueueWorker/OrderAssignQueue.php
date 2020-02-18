<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Drupal\mailchimp_ecommerce\PromoHandler;

/**
 * Updates Orders in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_order_assign_queue",
 *   title = @Translation("Mailchimp Ecommerce Order Assign Queue"),
 *   cron = {"time" = 120}
 * )
 */
class OrderAssignQueue extends QueueWorkerBase {

  /**
   * @inheritDoc
   */
  public function processItem($data)
  {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $data->getOrder();

    // An anonymous user has logged in or created an account after populating
    // a cart with items. This is the first point we can send this cart to
    // Mailchimp as we are now able to get the user's email address.
    $account = $data->getAccount();
    $customer['email_address'] = $account->getEmail();
    $billing_profile = $order->getBillingProfile();

    $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);

    $this->customer_handler->addOrUpdateCustomer($customer);

    // Mailchimp considers any order to be a cart until the order is complete.
    // This order is created as a cart in Mailchimp when assigned to the user.
    $order_data = $this->order_handler->buildOrder($order, $customer);

    // Add cart item price to order data.
    if (!isset($order_data['currency_code'])) {
      /** @var \Drupal\commerce_price\Price $price */
      $price = $order->getTotalPrice();

      if ($price) {
        $order_data['currency_code'] = $price->getCurrencyCode();
        $order_data['order_total'] = $price->getNumber();
      }
    }

    $order_data['checkout_url'] = Url::fromRoute('commerce_checkout.form',
      ['commerce_order' => $order->id()], ['absolute' => TRUE])->toString();

    $this->cart_handler->addOrUpdateCart($order->id(), $customer, $order_data);
  }
}