<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal;
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
use Drupal\commerce_cart\Event\CartEmptyEvent;

/**
 * Event Subscriber for Commerce Carts.
 */
class CartEventSubscriber implements EventSubscriberInterface {

  public function cartEventResponse($event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\CartQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_cart_queue');

    $cart = $event->getCart();

    if( $cart->getEmail() !== null ) {
      $data = [
        'order_id' => $cart->id(),
        'email' => $cart->getEmail(),
        'event' => gettype($event),
        'order_item_id' => null,
      ];

      if(method_exists($event, 'getOrderItem')) {
        $data['order_item_id'] = $event->getOrderItem()->id();
      }

      $queue->createItem($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CartEvents::CART_ENTITY_ADD][] = ['cartEventResponse'];
    $events[CartEvents::CART_ORDER_ITEM_UPDATE][] = ['cartEventResponse'];
    $events[CartEvents::CART_ORDER_ITEM_REMOVE][] = ['cartEventResponse'];
    $events[CartEvents::CART_EMPTY][] = ['cartEventResponse'];

    return $events;
  }

}
