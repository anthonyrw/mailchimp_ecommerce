<?php

namespace Drupal\mailchimp_ecommerce_commerce;

/**
 * Batch process handler for syncing product data to Mailchimp.
 */
class BatchSyncProducts {

  public static function syncProducts($product_ids, &$context) {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = count($product_ids);
      $context['results']['product_ids'] = $product_ids;
    }

    $config = \Drupal::config('mailchimp.settings');
    $batch_limit = $config->get('batch_limit');

    /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
    $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

    $batch = array_slice($context['results']['product_ids'], $context['sandbox']['progress'], $batch_limit);

    foreach ($batch as $product_id) {
      /** @var \Drupal\commerce_product\Entity\Product $product */
      $product = \Drupal\commerce_product\Entity\Product::load($product_id);

      $title = (!empty($product->get('title')->value)) ? $product->get('title')->value : '';
      $type = (!empty($product->get('type')->value)) ? $product->get('type')->value : '';

      /** @var \Drupal\mailchimp_ecommerce\ProductHandler $product_handler */
      $product_handler = \Drupal::service('mailchimp_ecommerce.product_handler');

      $url = $product_handler->buildProductUrl($product);
      $variants = $product_handler->buildProductVariants($product);

      $image_url = $product_handler->getProductImageUrl($product);

      $description = $product_handler->getProductDescription($product);
      // If description is null, use empty string instead
      $description = empty($description) ? '' : $description;

      try {
        $mc_ecommerce->getProduct(mailchimp_ecommerce_get_store_id(), $product_id);
        $product_handler->updateProduct($product_id, $title, $url, $image_url, $description, $type, $variants);
      } catch(\Exception $e) {
        if ($e->getCode() == 404) {
          $product_handler->addProduct($product_id, $title, $url, $image_url, $description, $type, $variants);
        }
      }

      $context['sandbox']['progress']++;

      $context['message'] = t('Sent @count of @total products to Mailchimp', [
        '@count' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['total'],
      ]);

      $context['finished'] = ($context['sandbox']['progress'] / $context['sandbox']['total']);
    }
  }

  public static function deleteProducts() {
    /** @var \Drupal\mailchimp_ecommerce\ProductHandler $product_handler */
    $product_handler = \Drupal::service('mailchimp_ecommerce.product_handler');
    $product_ids = $product_handler->getProductIds();

    foreach ($product_ids as $product_id) {
      try {
        $product_handler->deleteProduct($product_id);
      } catch (\Exception $e) {
        \Drupal::logger('mailchimp_ecommerce')->error($e->getMessage());
      }
    }

  }

}
