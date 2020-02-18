<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;



/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_product_delete_queue",
 *   title = @Translation("Mailchimp Ecommerce Product Delete Queue"),
 *   cron = {"time" = 120}
 * )
 */
class ProductDeleteQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    $product = $data->getProduct();
    $product_id = $product->get('product_id')->value;
    $this->product_handler->deleteProduct($product_id);
  }
}