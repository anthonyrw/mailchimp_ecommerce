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
 *   id = "mailchimp_ecommerce_commerce_order_update_queue",
 *   title = @Translation("Mailchimp Ecommerce Order Update Queue"),
 *   cron = {"time" = 120}
 * )
 */
class OrderUpdateQueue extends QueueWorkerBase {

  /**
   * @inheritDoc
   */
  public function processItem($data)
  {
    $customer = [];

    // Handle guest orders at the checkout review step - first time the user's
    // email address is available.
    if ($data->get('checkout_step')->value === 'review'
        && empty($data->getCustomer()->id()) ) {

      $customer['email_address'] = $data->getEmail();
      if (!empty($customer['email_address'])) {
        $billing_profile = $data->getBillingProfile();
        $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);
        $this->customer_handler->addOrUpdateCustomer($customer);
      }

      $order_data = $this->order_handler->buildOrder($data, $customer);

      // Add cart item price to order data.
      if (!isset($order_data['currency_code'])) {
        /** @var Price $price */
        $price = $data->getPrice();

        $order_data['currency_code'] = $price->getCurrencyCode();
        $order_data['order_total'] = $price->getNumber();
      }

      $order_data['checkout_url'] = Url::fromRoute('commerce_checkout.form', ['commerce_order' => $data->id()], ['absolute' => TRUE])->toString();
      $this->cart_handler->addOrUpdateCart($data->id(), $customer, $order_data);
    }

    // if the field does not exist, throw an error
    if(!$data->hasField('field_mailchimp_order_id')) {
      mailchimp_ecommerce_log_error_message('Order type ' . $data->getEntityTypeId() . ' is missing field_mailchimp_order_id.');
      return;
    }

    // When the order has been placed, replace cart in Mailchimp with order.
    $order_state = $data->get('state')->value;
    if($order_state !== 'draft') {
      if (empty($data->get('field_mailchimp_order_id')->getValue())) {
        // TODO Order has not been synced with Mailchimp, we need to do this before updating should be completed now.
        $data->set('field_mailchimp_order_id', $data->id());
        $data->save();
      }
      else {
        // Order exists in Mailchimp. We should update it.
        $order_data['id'] = $data->id();
        if ($order_state === 'validation' || $order_state === 'fulfillment') {
          // while in validation and fulfillment states, make sure all order
          // items in Drupal match order lines in Mailchimp.
          $customer['email_address'] = $data->getEmail();
          $customer = $this->customer_handler->buildCustomer($customer, $data->getBillingProfile());
          $order_data = $this->order_handler->buildOrder($data, $customer);
        }
        elseif ($order_state === 'completed') {
          $order_data['processed_at_foreign'] = date('c');
          $order_data['fulfillment_status'] = 'shipped';
        }
        elseif ($order_state === 'canceled') {
          $order_data['cancelled_at_foreign'] = date('c');
          $order_data['financial_status'] = 'cancelled';
        }
        $this->order_handler->updateOrder($data->id(), $order_data);
      }
    }
  }
}