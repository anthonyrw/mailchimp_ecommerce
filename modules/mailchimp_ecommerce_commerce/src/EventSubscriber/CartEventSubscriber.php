<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\CustomerHandler;
use Drupal\mailchimp_ecommerce\OrderHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber for Commerce Carts.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * Respond to event fired after adding a cart item.
   *
   * Initial cart creation in Mailchimp needs to happen when the first cart
   * item is added. This is because we can't rely on the total price being
   * available when the Commerce Order itself is first created.
   */
  public function cartAdd(CartEntityAddEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\CartAddQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_cart_add_queue');
    $queue->createItem($event);
  }

  /**
   * Respond to event fired after updating a cart item.
   */
  public function cartItemUpdate(CartOrderItemUpdateEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\CartAddQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_cart_item_add_queue');
    $queue->createItem($event);
  }

  /**
   * Respond to event fired after removing a cart item.
   */
  public function cartItemRemove(CartOrderItemRemoveEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\CartAddQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_cart_item_remove_queue');
    $queue->createItem($event);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CartEvents::CART_ENTITY_ADD][] = ['cartAdd'];
    $events[CartEvents::CART_ORDER_ITEM_UPDATE][] = ['cartItemUpdate'];
    $events[CartEvents::CART_ORDER_ITEM_REMOVE][] = ['cartItemRemove'];

    return $events;
  }

}
