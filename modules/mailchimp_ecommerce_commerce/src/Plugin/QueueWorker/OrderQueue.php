<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\Coupon;

/**
 * Updates Orders in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_order_queue",
 *   title = @Translation("Mailchimp Ecommerce Order Queue"),
 *   cron = {"time" = 120}
 * )
 */
class OrderQueue extends QueueWorkerBase {

  private $order;
  private $order_state;

  /**
   * {@inheritDoc}
   */
  final public function processItem($data) : void
  {
      $this->order_id = $data['order_id'];
      $this->email = $data['email'];
      $this->event = $data['event'];

      if( $this->event === 'OrderPlacedEvent') {
        $this->orderPlaced();
      }

      elseif($this->event === 'OrderPaidEvent') {
        $this->orderPaid();
      }

      elseif ($this->event === 'OrderUpdatedEvent') {
        /** @var OrderInterface $order */
        $this->order = Order::load($this->order_id);
        $this->order_state = $this->order->getState()->getValue();
        if($this->order_state !== 'draft') {
          $this->placedOrderUpdated();
        }
        else {
          $this->cartUpdated();
        }
      }
  }

  /**
   * When the order is placed, delete cart and add order in Mailchimp
   */
  private function orderPlaced() : void
  {

    $order = Order::load($this->order_id);
    assert($order instanceof  Order);

    try {
      // Remove the cart, because the order has been placed
      $this->cart_handler->deleteCart($this->order_id);
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error in deleteCart from Mailchimp for order # ' . $this->order_id);
    }

    // Email address should always be available on checkout completion.
    $customer['email_address'] = $order->getEmail();
    $billing_profile = $order->getBillingProfile();
    $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);
    $order_data = $this->order_handler->buildOrder($order, $customer);
    $order_data['financial_status'] = 'pending';

    try {
      // Update the customer's total order count and total amount spent.
      $this->customer_handler->incrementCustomerOrderTotal($customer['email_address'], $order_data['order_total']);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error on incrementCustomerOrderTotal from Mailchimp for order # ' . $this->order_id);
    }

    try {
      // Add the order to Mailchimp for the first time
      $this->order_handler->addOrder($this->order_id, $customer, $order_data);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error in addOrder to Mailchimp call for order # ' . $this->order_id);
    }

    // if a promo was used, update it now
    foreach($order->get('coupons') as $coupon) {

      $coupon_id = $coupon->get('target_id')->getCastedValue();
      /** @var Coupon $coupon */
      $coupon = Coupon::load($coupon_id);
      $promotion_id = $coupon->getPromotionId();
      $promo_code = $this->promo_handler->buildPromoCode($coupon);
      $promo_code['usage_count'] = (int) ($this->promo_handler->getPromoCode($promotion_id, $coupon_id)->usage_count) + 1;

      try {
        // Update promo code usage
        $this->promo_handler->updatePromoCode($promotion_id, $promo_code);
      }
      catch (\Exception $e) {
        mailchimp_ecommerce_log_error_message('There was an error trying to update a promo code on order ' . $this->order_id);
      }
    }
  }

  /**
   * Update a placed order in Mailchimp
   */
  private function placedOrderUpdated() : void
  {
    $order_data['id'] = $this->order_id;
    $update = false;

    if ($this->order_state === 'validation' || $this->order_state === 'fulfillment') {
      // while in validation and fulfillment states, make sure all order
      // items in Drupal match order lines in Mailchimp.
      $customer['email_address'] = $this->email;
      $customer = $this->customer_handler->buildCustomer($customer, $this->order->getBillingProfile());
      $order_data = $this->order_handler->buildOrder($this->order, $customer);
      $update = true;
    }
    elseif ($this->order_state === 'completed') {
      $order_data['processed_at_foreign'] = date('c');
      $order_data['fulfillment_status'] = 'shipped';
      $update = true;
    }
    elseif ($this->order_state === 'canceled') {
      $order_data['cancelled_at_foreign'] = date('c');
      $order_data['financial_status'] = 'cancelled';
      $update = true;
    }

    try {
      // We should only be updating if something has changed
      if ($update) {
        $this->order_handler->updateOrder($this->order_id, $order_data);
      }
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error trying to update a placed order ' . $this->order_id);
    }
  }

  /**
   * Update the financial status of an order in Mailchimp
   */
  private function orderPaid() : void {
    $order_data['id'] = $this->order_id;
    $order_data['financial_status'] = 'paid';
    try {
      $this->order_handler->updateOrder($this->order_id, $order_data);
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error trying to update a placed order ' . $this->order_id);
    }
  }
}