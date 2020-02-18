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
  public function productInsert(ProductEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductInsertQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_insert_queue');
    $queue->createItem($event);
  }

  /**
   * Respond to event fired after updating an existing product.
   */
  public function productUpdate(ProductEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductUpdateQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_update_queue');
    $queue->createItem($event);

  }

  /**
   * Respond to event fired after deleting a product.
   */
  public function productDelete(ProductEvent $event) {
    /** @var \Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker\ProductDeleteQueue $queue */
    $queue = Drupal::queue('mailchimp_ecommerce_commerce_product_delete_queue');
    $queue->createItem($event);
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
