<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferBase;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\FixedAmountOffTrait;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PercentageOffTrait;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Url;

/**
 * Promo rule and promo code handler.
 */
class PromoHandler implements PromoHandlerInterface {

  /**
   * @inheritdoc
   */
  public function getPromoRule($promo_rule_id){
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot get a promo rule without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $promo_rule = $mc_ecommerce->getPromoRule($store_id, $promo_rule_id);
      return $promo_rule;
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to get promo rule: ' . $e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }

    return NULL;
  }

  /**
   * @inheritdoc
   */
  public function addPromoRule(array $promo_rule){
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a promo rule without a store ID.');
      }
      $promo_rule['created_at_foreign'] = date('c');
      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->addPromoRule($store_id, $promo_rule);
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to add promo rule: ' . $e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function updatePromoRule(array $promo_rule){
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot update a promo rule without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->updatePromoRule($store_id, $promo_rule);

  }

  /**
   * @inheritdoc
   */
  public function buildPromoRule(PromotionInterface $promotion) {
    $promo_rule = [];

    $promo_rule['id'] = $promotion->id(); // required
    $promo_rule['title'] = $promotion->getName();
    $promo_rule['description'] = !empty($promotion->getDescription()) ? $promotion->getDescription() : ""; // required
    $promo_rule['starts_at'] = !empty($promotion->getStartDate()) ? $promotion->getStartDate()->format('c') : "";
    $promo_rule['ends_at'] = $promotion->getEndDate()->format('c') == "0000-00-00 00:00:00" ? "2030-01-01T00:00:00+00:00" : $promotion->getEndDate()->format('c');
    $promo_rule['enabled'] = $promotion->isEnabled();
    $promo_rule['updated_at_foreign'] = date('c');

    $offer = $promotion->getOffer();
    $offer_config = $offer->getConfiguration();
//    \Drupal::logger('mc')->notice('<pre>' . $promotion->getName() . '<code>'. print_r($offer->getConfiguration(), true) . '</code></pre>');

    if(array_key_exists('amount', $offer_config)) {
      $promo_rule['type'] = 'fixed'; // required
      $promo_rule['amount'] = $offer_config['amount']['number']; // required
    }
    elseif(array_key_exists('percentage', $offer_config)) {
      $promo_rule['type'] = 'percentage'; // required
      $promo_rule['amount'] = $offer_config['percentage']; // required
    }
    // TODO figure out how to make this work with shipping promotions
    else {
      $promo_rule['type'] = 'fixed';
      $promo_rule['amount'] = 0;
    }

    if ($offer->getEntityTypeId() == 'commerce_order') {
      $promo_rule['target'] = 'total'; // required
    }
    elseif ($offer->getEntityTypeId() == 'commerce_order_item') {
      $promo_rule['target'] = 'per_item'; // required
    }
    else {
      $promo_rule['target'] = 'shipping';
    }
//    \Drupal::logger('mc_promo_rule')->notice('<pre>' . $promotion->getName() . ' <code>'. print_r($promo_rule, true) . '</code></pre>');
    return $promo_rule;
  }

  /**
   * @inheritdoc
   */
  public function getPromoCode($promo_rule_id, $promo_code_id) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot get a promo code without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $promo_rule = $mc_ecommerce->getPromoCode($store_id, $promo_rule_id, $promo_code_id);
      return $promo_rule;
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to get promo code: ' . $e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }

    return NULL;
  }

  /**
   * @inheritdoc
   */
  public function addPromoCode($promo_rule_id, array $promo_code) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a promo rule without a store ID.');
      }
      $promo_code['created_at_foreign'] = date('c');
      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->addPromoCode($store_id, $promo_rule_id, $promo_code);
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to add promo code: ' . $e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function updatePromoCode($promo_rule_id, array $promo_code) {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot update a promo rule without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->updatePromoCode($store_id, $promo_rule_id, $promo_code);

  }

  /**
   * @inheritdoc
   */
  public function buildPromoCode(CouponInterface $coupon, $redemption_url = NULL) {
    $promo_code = [];

    $promo_code['id'] = $coupon->id(); // required
    $promo_code['code'] = $coupon->getCode(); // required

    if(!empty($redemption_url)) {
      $promo_code['redemption_url'] = $redemption_url; // required
    }
    else {
      $promo_code['redemption_url'] = Url::fromRoute('<front>', [], ['absolute' => TRUE])
        ->toString(); // required
    }
    $promo_code['enabled'] = $coupon->isEnabled();
    $promo_code['updated_at_foreign'] = date('c');

    return $promo_code;
  }

}