<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Entity\PromotionInterface;

/**
 * Interface for PromoHandler
 */
interface PromoHandlerInterface {

  /**
   * Gets a promo rule from the current Mailchimp store.
   *
   * @param string $promo_rule_id
   *   The promo rule ID.
   *
   * @return object
   *   The promo rule.
   */
  public function getPromoRule($promo_rule_id);

  /**
   * Adds a new promo rule to the current Mailchimp store.
   *
   * @param $promo_rule_id
   *   The promo rule ID.
   * @param array $promo_rule
   *   Associative array of promo rule information.
   *   - id (string) A unique identifier for the promo rule.
   *   - description (string) A description of the promo rule
   *   - amount (number) The amount of the promo code discount.
   *       If ‘type’ is ‘fixed’, the amount is treated as a monetary value.
   *       If ‘type’ is ‘percentage’, amount must be a decimal value between
   *       0.0 and 1.0, inclusive.
   *   - type (string) Type of discount. For free shipping, set to fixed.
   *       Possible values: fixed, percentage
   *   - target (string) The target that the discount applies to. Possible
   *       values: per_item, total, shipping
   *
   * @see https://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/promo-rules/#create-post_ecommerce_stores_store_id_promo_rules
   */
  public function addPromoRule(array $promo_rule);

  /**
   * Update a promo rule in the current Mailchimp store.
   *
   * @param $promo_rule_id
   *   The promo rule ID.
   * @param array $promo_rule
   *   An associative array of promo rule information.
   *   - id (string) A unique identifier for the promo rule.
   *   - description (string) A description of the promo rule.
   *   - amount (number) The amount of the promo code discount.
   *       If ‘type’ is ‘fixed’, the amount is treated as a monetary value.
   *       If ‘type’ is ‘percentage’, amount must be a decimal value between
   *       0.0 and 1.0, inclusive.
   *   - type (string) Type of discount. For free shipping, set to fixed.
   *       Possible values: fixed, percentage.
   *   - target (string) The target that the discount applies to. Possible
   *       values: per_item, total, shipping.
   *
   * @throws \Mailchimp\MailchimpAPIException
   */
  public function updatePromoRule(array $promo_rule);

  /**
   * Returns promo rule data formatted for use with Mailchimp.
   *
   * @param \Drupal\commerce_promotion\Entity\Promotion $promotion
   *   The Commerce Promotion.
   *
   * @return array
   *   Array of promo rule data.
   */
  public function buildPromoRule(PromotionInterface $promotion);

//  public function deletePromoRule();

  /**
   * Gets a promo code from the current Mailchimp store.
   *
   * @param string $promo_rule_id
   *   The promo rule ID.
   * @param string $promo_code_id
   *   The promo code ID.
   *
   * @return object
   *   The promo code.
   */
  public function getPromoCode($promo_rule_id, $promo_code_id);

  /**
   * Add a promo code in the current Mailchimp store.
   *
   * @param $promo_rule_id
   *   The promo rule ID.
   * @param array $promo_code
   *   An associative array of promo code information.
   *   - id (string) A unique identifier for the promo code.
   *   - code (string) The discount code.
   *   - redemption_url (string) The url that should be used in the promotion
   *       campaign.
   */
  public function addPromoCode($promo_rule_id, array $promo_code);

  /**
   * Update a promo code in the current Mailchimp store.
   *
   * @param $promo_rule_id
   *   The promo rule ID.
   * @param array $promo_code
   *   An associative array of promo code information.
   *   - id (string) A unique identifier for the promo code.
   *   - code (string) The discount code.
   *   - redemption_url (string) The url that should be used in the promotion
   *       campaign.
   *
   * @throws \Mailchimp\MailchimpAPIException
   */
  public function updatePromoCode($promo_rule_id, array $promo_code);

  /**
   * Returns promo code data formatted for use with Mailchimp.
   *
   * @param \Drupal\commerce_promotion\Entity\Coupon $coupon
   *   The Commerce Coupon.
   * @param string $redemption_url
   *   The url that should be used in the promotion campaign. Defaults to
   *     <front> if NULL.
   *
   * @return array
   *   Array of promo code data.
   */
  public function buildPromoCode(CouponInterface $coupon, $redemption_url = NULL);

//  public function deletePromoCode();
}