<?php

namespace Drupal\mailchimp_ecommerce_promo_code\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class MailchimpEcommercePromoCodeSyncPromotions extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailchimp_ecommerce_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['sync_promotions'] = [
      '#type' => 'checkbox',
      '#title' => t('Sync Promotions'),
      '#description' => t('Sync all existing promotions to Mailchimp.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync with Mailchimp'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->_submitForm($form, $form_state);
  }

  /**
   * Processes data sync to Mailchimp.
   *
   * Syncing data to Mailchimp is specific to the shopping cart integration.
   * You should implement this function in your integration to process the
   * data sync.
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
          '\Drupal\mailchimp_ecommerce_promo_code\BatchSyncPromotions::syncPromotions',
          [$promo_rule_ids],
        ];
      }

      batch_set($batch);
    }
  }
}
