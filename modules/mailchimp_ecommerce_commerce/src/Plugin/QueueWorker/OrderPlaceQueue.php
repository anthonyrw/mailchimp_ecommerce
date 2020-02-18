<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Core\Url;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Drupal\mailchimp_ecommerce\PromoHandler;

/**
 * Updates Orders in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_order_place_queue",
 *   title = @Translation("Mailchimp Ecommerce Order Place Queue"),
 *   cron = {"time" = 120}
 * )
 */
class OrderPlaceQueue extends QueueWorkerBase {

  /**
   * @inheritDoc
   */
  public function processItem($data)
  {
    $customer = [];
    $order = $data;
    try {
      if( $order !== null ) {
        $this->cart_handler->deleteCart( $order->id() );

        // Email address should always be available on checkout completion.
        $customer['email_address'] = $order->getEmail();
        $billing_profile = $order->getBillingProfile();
        $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);
        $order_data = $this->order_handler->buildOrder($order, $customer);
        $order_data['financial_status'] = 'pending';

        // Update the customer's total order count and total amount spent.
        $this->customer_handler->incrementCustomerOrderTotal($customer['email_address'], $order_data['order_total']);
        $this->order_handler->addOrder($order->id(), $customer, $order_data);

        // if a promo was used, update it now
        foreach($order->get('coupons') as $coupon) {
          try {
            $coupon_id = $coupon->get('target_id')->getCastedValue();
            $promotion_id = Coupon::load($coupon_id)->getPromotionId();
            $promo_code = $this->promo_handler->buildPromoCode(Coupon::load($coupon_id));
            $promo_code['usage_count'] = (int) ($this->promo_handler->getPromoCode($promotion_id, $coupon_id)->usage_count) + 1;
            $this->promo_handler->updatePromoCode($promotion_id, $promo_code);
          } catch (\Exception $e) {
            mailchimp_ecommerce_log_error_message('There was an error trying to update a promo code on order ' . $order->id());
          }
        }
      }
    } catch(\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error trying to create order ' . $order->id());
    }
  }

}