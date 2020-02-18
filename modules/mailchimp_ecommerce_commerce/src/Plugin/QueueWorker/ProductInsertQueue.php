<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_product\Entity\Product;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_product_insert_queue",
 *   title = @Translation("Mailchimp Ecommerce Product Insert Queue"),
 *   cron = {"time" = 120}
 * )
 */
class ProductInsertQueue extends QueueWorkerBase
{
  public function processItem($data)
  {
    /** @var Product $product */
    $product = $data->getProduct();

    $product_id = $product->get('product_id')->value;
    $title = (!empty($product->get('title')->value)) ? $product->get('title')->value : '';
    // TODO Fix Type
    $type = (!empty($product->get('type')->value)) ? $product->get('type')->value : '';

    $variants = $this->product_handler->buildProductVariants($product);
    $url = $this->product_handler->buildProductUrl($product);
    $image_url = $this->product_handler->getProductImageUrl($product);
    $description = $this->product_handler->getProductDescription($product);

    $this->product_handler->addProduct($product_id, $title, $url, $image_url, $description, $type, $variants);
  }

}