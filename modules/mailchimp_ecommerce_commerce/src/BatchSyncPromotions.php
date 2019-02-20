<?php

namespace Drupal\mailchimp_ecommerce_commerce;

use Drupal\commerce_promotion\Entity\Promotion;

/**
 * Batch process handler for syncing promotion data to Mailchimp.
 */
class BatchSyncPromotions {

  /**
   * Batch processor for promotion sync.
   *
   * @param array $order_ids
   *   IDs of promotions to sync.
   * @param array $context
   *   Batch process context; stores progress data.
   */
  public static function syncPromotions($promotion_ids, &$context) {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = count($promotion_ids);
      $context['results']['promotion_ids'] = $promotion_ids;
    }

    $config = \Drupal::config('mailchimp.settings');
    $batch_limit = $config->get('batch_limit');

    $batch = array_slice($context['results']['promotion_ids'], $context['sandbox']['progress'], $batch_limit);

    /** @var \Drupal\mailchimp_ecommerce\PromoHandler $promo_handler */
    $promo_handler = \Drupal::service('mailchimp_ecommerce.promo_handler');

    foreach($batch as $promotion_id) {
      $promotion = Promotion::load($promotion_id);
      $promo_rule = $promo_handler->buildPromoRule($promotion);
      try {
        $promo_handler->updatePromoRule($promo_rule);
      }
      catch (\Exception $e) {
        if($e->getCode() == 404) {
          $promo_handler->addPromoRule($promo_rule);
        }
        else {
          \Drupal::logger('mc_ecommerce')->error('Something went wrong with promo rule! ' . $e->getMessage());
        }
      }
      foreach($promotion->getCoupons() as $coupon) {
        $promo_code = $promo_handler->buildPromoCode($coupon);
        try {
          $promo_handler->updatePromoCode($promotion_id, $promo_code);
        } catch (\Exception $e) {
          if ($e->getCode() == 404) {
            $promo_handler->addPromoCode($promotion_id, $promo_code);
          }
          else {
            \Drupal::logger('mc_ecommerce')->error('Something went wrong with promo code! ' . $e->getMessage());
          }
        }
      }

      $context['sandbox']['progress']++;

      $context['message'] = t('Sent @count of @total promotions to Mailchimp', [
        '@count' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['total'],
      ]);

      $context['finished'] = ($context['sandbox']['progress'] / $context['sandbox']['total']);
    }
  }
}