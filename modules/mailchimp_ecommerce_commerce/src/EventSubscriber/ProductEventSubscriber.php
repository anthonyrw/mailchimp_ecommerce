<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber for Commerce Products.
 */
class ProductEventSubscriber implements EventSubscriberInterface {
  /**
   * Respond to event fired after saving a new product.
   */
  private function productInsert(ProductEvent $event) : void
  {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_queue');
    $data = [
      'product_id' => $event->getProduct()->id(),
      'event' => 'ProductInsertEvent'
    ];
    $queue->createItem($data);
  }

  /**
   * Respond to event fired after updating an existing product.
   */
  private function productUpdate(ProductEvent $event) : void
  {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_queue');
    $data = [
      'product_id' => $event->getProduct()->id(),
      'event' => 'ProductUpdateEvent'
    ];
    $queue->createItem($data);

  }

  /**
   * Respond to event fired after deleting a product.
   * @param ProductEvent $event
   */
  private function productDelete(ProductEvent $event) : void
  {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_queue');
    $data = [
      'product_id' => $event->getProduct()->id(),
      'event' => 'ProductDeleteEvent'
    ];
    $queue->createItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ProductEvents::PRODUCT_INSERT][] = ['productInsert'];
    $events[ProductEvents::PRODUCT_UPDATE][] = ['productUpdate'];
    $events[ProductEvents::PRODUCT_DELETE][] = ['productDelete'];

    return $events;
  }

}
