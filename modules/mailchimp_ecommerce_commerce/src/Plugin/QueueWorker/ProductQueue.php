<?php

namespace Drupal\mailchimp_ecommerce_commerce\Plugin\QueueWorker;

use Drupal\commerce_product\Entity\Product;

/**
 * Updates Carts in Mailchimp
 *
 * @QueueWorker(
 *   id = "mailchimp_ecommerce_commerce_product_queue",
 *   title = @Translation("Mailchimp Ecommerce Product Queue"),
 *   cron = {"time" = 120}
 * )
 */
class ProductQueue extends QueueWorkerBase
{
  private $product_id;
  private $product;
  private $variants;
  private $title;
  private $type;
  private $url;
  private $image_url;
  private $description;


  final public function processItem($data) : void
  {
    $this->product_id = $data['product_id'];
    $this->product = Product::load($this->product_id);
    $this->event = $data['event'];

    if( $this->event === 'ProductInsertEvent' ) {
      $this->productInsert();
    }
    elseif( $this->event === 'ProductUpdateEvent' ) {
      $this->productUpdate();
    }
    elseif( $this->event === 'ProductDeleteEvent' ) {
      $this->productDelete();
    }
  }

  private function defineParams() : void {
    $this->title = (!empty($this->product->get('title')->value)) ? $this->product->get('title')->value : '';
    // TODO Fix Type
    $this->type = (!empty($this->product->get('type')->value)) ? $this->product->get('type')->value : '';
    $this->variants = $this->product_handler->buildProductVariants($this->product);
    $this->url = $this->product_handler->buildProductUrl($this->product);
    $this->image_url = $this->product_handler->getProductImageUrl($this->product);
    $this->description = $this->product_handler->getProductDescription($this->product);
  }

  private function productInsert() : void
  {
    $this->defineParams();
    try {
      // Add a new product and variations to mailchimp
      $this->product_handler->addProduct($this->product, $this->title, $this->url, $this->image_url, $this->description, $this->type, $this->variants);
    }
    catch (\Exception $e){
      mailchimp_ecommerce_log_error_message('There was an error trying to add a product to Mailchimp ' . $this->product_id);
    }
  }

  private function productUpdate() : void
  {
    $this->defineParams();

    try {
      // Update the existing product and variant.
      $this->product_handler->updateProduct($this->product, $this->title, $this->url, $this->image_url, $this->description, $this->type, $this->variants);
    }
    catch (\Exception $e){
      // This should be redundant ... but just in case
      // If we try to update a product that doesn't exist, add it to the product insert queue
      if( $e->getCode() === '404') {
        $queue = \Drupal::queue('mailchimp_ecommerce_commerce_product_insert_queue');
        $queue->createItem([
          'product_id' => $this->product_id,
          'event' => 'ProductInsertEvent',
        ]);
      }
    }
  }

  private function productDelete() : void
  {
    try {
      $this->product_handler->deleteProduct($this->product_id);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Encountered an error trying to delete product with id: ' . $this->product_id);
    }
  }

}