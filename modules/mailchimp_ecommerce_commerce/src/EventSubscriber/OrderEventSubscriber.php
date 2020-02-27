<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderAssignQueue;
use Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderPaidQueue;
use Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderPlaceQueue;
use Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderUpdateQueue;
use Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\OrderGuestAssignQueue;

/**
 * Event Subscriber for Commerce Orders.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array
  {
    $events['commerce_order.place.post_transition'] = ['orderPlace'];
    $events[OrderEvents::ORDER_UPDATE][] = ['orderUpdate'];
    $events[OrderEvents::ORDER_ASSIGN][] = ['orderAssign'];
    $events[OrderEvents::ORDER_PAID][] = ['orderPaid'];

    return $events;
  }

  public function orderPlace(OrderEvent $event) {
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_queue');
    assert($queue instanceof OrderQueue);
    $data = [
      'order_id' => $event->getOrder()->id(),
      'email' => $event->getOrder()->getEmail(),
      'event' => 'OrderPlacedEvent',
    ];
    $queue->createItem( $data );
  }

  public function orderUpdate(OrderEvent $event) {
    // don't create a queue item if certain conditions haven't been met
    $state = $event->getOrder()->getState()->getValue();
    switch($state) {
      case 'validation':
      case 'fulfillment':
      case 'completed':
      case 'canceled':
        $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_queue');
        assert($queue instanceof OrderQueue);
        $data = [
          'order_id' => $event->getOrder()->id(),
          'email' => $event->getOrder()->getEmail(),
          'event' => 'OrderUpdatedEvent',
        ];
        $queue->createItem( $data );
        break;
      default:
        break;
    }
  }

  public function orderAssign(OrderAssignEvent $event) {

  }

  public function orderPaid(OrderEvent $event) {
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_order_queue');
    assert($queue instanceof OrderQueue);
    $data = [
      'order_id' => $event->getOrder()->id(),
      'email' => $event->getOrder()->getEmail(),
      'event' => 'OrderPaidEvent',
    ];
    $queue->createItem( $data );
  }
}
