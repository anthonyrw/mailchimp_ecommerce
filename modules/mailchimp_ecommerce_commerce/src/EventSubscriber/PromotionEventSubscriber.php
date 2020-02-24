<?php

namespace Drupal\mailchimp_ecommerce_commerce\EventSubscriber;

use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Event\PromotionEvent;
use Drupal\commerce_promotion\Event\PromotionEvents;
use Drupal\commerce_promotion\Event\CouponEvent;
use Drupal\mailchimp_ecommerce\PromoHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Commerce Promotions.
 */
class PromotionEventSubscriber implements EventSubscriberInterface {

  /**
   * The Promo Handler.
   *
   * @var \Drupal\mailchimp_ecommerce\PromoHandler
   */
  private $promo_handler;

  /**
   * PromotionEventSubscriber constructor
   *
   * @param \Drupal\mailchimp_ecommerce\PromoHandler $promo_handler
   *   The promo handler.
   */
  public function __construct(PromoHandler $promo_handler) {
    $this->promo_handler = $promo_handler;
  }


  /**
   * Respond to event fired after saving a new promotion.
   */
  public function promoRuleInsert(PromotionEvent $event) {
    /** @var Promotion $promotion */
    $promotion = $event->getPromotion();
    $promo_rule = $this->promo_handler->buildPromoRule($promotion);
    $this->promo_handler->addPromoRule($promo_rule);
  }

  /**
   * Respond to event fired after updating a promotion.
   */
  public function promoRuleUpdate(PromotionEvent $event) {
    /** @var Promotion $promotion */
    $promotion = $event->getPromotion();
    $promo_rule = $this->promo_handler->buildPromoRule($promotion);
    try {
      $this->promo_handler->updatePromoRule($promo_rule);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Could not update promo rule: ' . $promotion->getCode());
    }
  }

  /**
   * Respond to event fired after deleting a promotion.
   */
  public function promoRuleDelete(PromotionEvent $event) {
    // TODO: Process deleted promotion.
  }

  /**
   * Respond to event fired after saving a new promotion.
   */
  public function promoCodeInsert(CouponEvent $event) {
    $promo_rule_id = $event->getCoupon()->getPromotionId();
    $coupon = $event->getCoupon();
    $promo_code = $this->promo_handler->buildPromoCode($coupon);
    $this->promo_handler->addPromoCode($promo_rule_id, $promo_code);

  }

  /**
   * Respond to event fired after updating a coupons in a promotion.
   */
  public function promoCodeUpdate(CouponEvent $event) {
    $promo_rule_id = $event->getCoupon()->getPromotionId();
    $coupon = $event->getCoupon();
    $promo_code = $this->promo_handler->buildPromoCode($coupon);
    try {
      $this->promo_handler->updatePromoCode($promo_rule_id, $promo_code);
    } catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Could not update promo code: ' . $coupon->getCode());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PromotionEvents::PROMOTION_INSERT][] = ['promoRuleInsert'];
    $events[PromotionEvents::PROMOTION_UPDATE][] = ['promoRuleUpdate'];
    $events[PromotionEvents::PROMOTION_DELETE][] = ['promoRuleDelete'];
    $events[PromotionEvents::COUPON_INSERT][] = ['promoCodeInsert'];
    $events[PromotionEvents::COUPON_UPDATE][] = ['promoCodeUpdate'];
    $events[PromotionEvents::COUPON_DELETE][] = ['promoCodeDelete'];

    return $events;
  }
}