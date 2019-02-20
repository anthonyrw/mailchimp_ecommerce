<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Drupal\mailchimp_ecommerce\PromoHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_promotion\Entity\Coupon;

/**
 * Event Subscriber for Commerce Orders.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The Order Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\OrderHandler
   */
  private $order_handler;

  /**
   * The Cart Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\CartHandler
   */
  private $cart_handler;

  /**
   * The Customer Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\CustomerHandler
   */
  private $customer_handler;

  /**
   * The Promo Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\PromoHandler
   */
  private $promo_handler;

  /**
   * OrderEventSubscriber constructor.
   *
   * @param \Drupal\mailchimp_ecommerce\OrderHandler $order_handler
   *   The Order Handler.
   * @param \Drupal\mailchimp_ecommerce\CartHandler $cart_handler
   *   The Cart Handler.
   * @param \Drupal\mailchimp_ecommerce\CustomerHandler $customer_handler
   *   The Customer Handler.
   * @param \Drupal\mailchimp_ecommerce\PromoHandler $promo_handler
   *   The Promo Handler.
   */
  public function __construct(OrderHandler $order_handler, CartHandler $cart_handler, CustomerHandler $customer_handler, PromoHandler $promo_handler) {
    $this->order_handler = $order_handler;
    $this->cart_handler = $cart_handler;
    $this->customer_handler = $customer_handler;
    $this->promo_handler = $promo_handler;
  }

  /**
   * Respond to event fired after updating an existing order.
   */
  public function orderUpdate(OrderEvent $event) {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $event->getOrder();
    $customer = [];

    // Handle guest orders at the checkout review step - first time the user's
    // email address is available.
    if (empty($order->getCustomer()->id()) && ($order->get('checkout_step')->value == 'review')) {
      $customer['email_address'] = $event->getOrder()->getEmail();
      if (!empty($customer['email_address'])) {
        $billing_profile = $order->getBillingProfile();
        $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);
        $this->customer_handler->addOrUpdateCustomer($customer);
      }

      $order_data = $this->order_handler->buildOrder($order, $customer);

      // Add cart item price to order data.
      if (!isset($order_data['currency_code'])) {
        /** @var Price $price */
        $price = $event->getEntity()->getPrice();

        $order_data['currency_code'] = $price->getCurrencyCode();
        $order_data['order_total'] = $price->getNumber();
      }

      $this->cart_handler->addOrUpdateCart($order->id(), $customer, $order_data);
    }

    // if the field does not exist, throw an error
    if(!$order->hasField('field_mailchimp_order_id')) {
      mailchimp_ecommerce_log_error_message('Order type ' . $order->getEntityTypeId() . ' is missing field_mailchimp_order_id.');
      return;
    }

    // When the order has been placed, replace cart in Mailchimp with order.
    $order_state = $order->get('state')->value;
    if($order_state == 'validation' || $order_state == 'fulfillment' || $order_state == 'completed' || $order_state == 'canceled') {
      if (empty($order->get('field_mailchimp_order_id')->getValue())) {
        // Order has not been synced with Mailchimp, it should be completed now.
        $this->firstCartToOrder($order);
        $order->set('field_mailchimp_order_id', $order->id());
      }
      else {
        // Order exists in Mailchimp. We should only update it.
        $order_data['id'] = $order->id();
        if ($order_state == 'validation' || $order_state == 'fulfillment') {

        }
        elseif ($order_state == 'completed') {
          $order_data['financial_status'] = 'paid';
          $order_data['fulfillment_status'] = 'shipped';
        }
        elseif ($order_state == 'canceled') {
          $order_data['financial_status'] = 'cancelled';
        }
        $this->order_handler->updateOrder($order->id(), $order_data);
      }
    }
  }

  /**
   * First time cart to order set up.
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   */
  private function firstCartToOrder(Order $order) {
    $customer = [];
    try {
      $this->cart_handler->deleteCart($order->id());

      $customer['email_address'] = $order->getEmail();
      $billing_profile = $order->getBillingProfile();
      $customer = $this->customer_handler->buildCustomer($customer, $billing_profile);
      $order_data = $this->order_handler->buildOrder($order, $customer);
      $order_data['financial_status'] = 'pending';

      $this->customer_handler->incrementCustomerOrderTotal($customer['email_address'], $order_data['order_total']);
      $this->order_handler->addOrder($order->id(), $customer, $order_data);

      // if a promo was used, update it now
      foreach($order->get('coupons') as $coupon) {
        try {
          $coupon_id = $coupon->get('target_id')->getCastedValue();
          $promotion_id = Coupon::load($coupon_id)->getPromotionId();
          $promo_code = $this->promo_handler->buildPromoCode(Coupon::load($coupon_id));
          $promo_code['usage_count'] = intval($this->promo_handler->getPromoCode($promotion_id, $coupon_id)->usage_count) + 1;
          $this->promo_handler->updatePromoCode($promotion_id, $promo_code);
        } catch (\Exception $e) {
          mailchimp_ecommerce_log_error_message('There was an error trying to update a promo code on order ' . $order->id());
        }
      }
    } catch(\Exception $e) {
      mailchimp_ecommerce_log_error_message('There was an error trying to create order ' . $order->id());
    }
  }

  /**
   * Respond to event fired after updating an order item in a placed order
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   */
  public function orderItemUpdate(OrderItemEvent $event) {

  }

  /**
   * Respond to event fired after inserting an order item into a placed order
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   */
  public function orderItemInsert(OrderItemEvent $event) {

  }

  /**
   * Respond to event fired after assigning an anonymous order to a user.
   */
  public function orderAssign(OrderAssignEvent $event) {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $event->getOrder();

    // An anonymous user has logged in or created an account after populating
    // a cart with items. This is the first point we can send this cart to
    // Mailchimp as we are now able to get the user's email address.
    $account = $event->getAccount();
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
      $price = $event->getOrder()->getTotalPrice();

      if($price) {
        $order_data['currency_code'] = $price->getCurrencyCode();
        $order_data['order_total'] = $price->getNumber();
        $this->cart_handler->addOrUpdateCart($order->id(), $customer, $order_data);
      }
    }

    $this->cart_handler->addOrUpdateCart($order->id(), $customer, $order_data);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrderEvents::ORDER_UPDATE][] = ['orderUpdate'];
    $events[OrderEvents::ORDER_ASSIGN][] = ['orderAssign'];

    // handle modifications to orders after they have been placed
    $events[OrderEvents::ORDER_ITEM_INSERT][] = ['orderItemInsert'];
    $events[OrderEvents::ORDER_ITEM_UPDATE][] = ['orderItemUpdate'];

    return $events;
  }

}
