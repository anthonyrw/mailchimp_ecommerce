<?php

namespace Drupal\mailchimp_ecommerce_commerce\Form;

use Drupal\mailchimp_ecommerce\Form\MailchimpEcommerceSyncPromotions;

class MailchimpEcommerceCommerceSyncPromotions extends MailchimpEcommerceSyncPromotions {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function _submitForm($form, $form_state) {
    if (!empty($form_state->getValue('sync_promotions'))) {
      $batch = [
        'title' => t('Adding promotions to Mailchimp'),
        'operations' => [],
      ];

      $query = \Drupal::entityQuery('commerce_promotion');
      $result = $query->execute();

      if (!empty($result)) {
        $promo_rule_ids = array_keys($result);

        $batch['operations'][] = [
          '\Drupal\mailchimp_ecommerce_commerce\BatchSyncPromotions::syncPromotions',
          [$promo_rule_ids],
        ];
      }

      batch_set($batch);
    }
  }

}
