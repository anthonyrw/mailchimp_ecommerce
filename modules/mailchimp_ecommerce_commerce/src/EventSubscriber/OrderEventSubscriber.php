<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;

/**
 * Event Subscriber for Commerce Orders.
 */
class OrderEventSubscriber implements EventSubscriberInterface {


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = ['orderPlace'];
    $events[OrderEvents::ORDER_UPDATE][] = ['orderUpdate'];
    $events[OrderEvents::ORDER_ASSIGN][] = ['orderAssign'];
    $events[OrderEvents::ORDER_PAID][] = ['orderPaid'];

    return $events;
  }

  public function orderPlace(OrderEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderPlaceQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_place_queue');
    $queue->createItem( $event->getOrder() );
  }

  public function orderUpdate(OrderEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderUpdateQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_update_queue');
    $queue->createItem( $event->getOrder() );
  }

  public function orderAssign(OrderAssignEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderAssignQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_assign_queue');
    $queue->createItem($event);
  }

  public function orderPaid(OrderEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderPaidQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_paid_queue');
    $queue->createItem($event->getOrder());
  }
}
