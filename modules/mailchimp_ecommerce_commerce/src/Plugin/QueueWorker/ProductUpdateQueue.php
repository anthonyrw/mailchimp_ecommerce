<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_product_update_queue",
 *   title = @Translation("Mailchimp Ecommerce Product Update Queue"),
 *   cron = {"time" = 120}
 * )
 */
class ProductUpdateQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    $product = $data->getProduct();

    $title = (!empty($product->get('title')->value)) ? $product->get('title')->value : '';
    // TODO Fix Type
    $type = (!empty($product->get('type')->value)) ? $product->get('type')->value : '';

    $variants = $this->product_handler->buildProductVariants($product);
    $url = $this->product_handler->buildProductUrl($product);
    $image_url = $this->product_handler->getProductImageUrl($product);
    $description = $this->product_handler->getProductDescription($product);

    // Update the existing product and variant.
    $this->product_handler->updateProduct($product, $title, $url, $image_url, $description, $type, $variants);
  }
}