<?php

/**
 * @file
 * Contains \Drupal\mailchimp_ecommerce_promo_code\Routing\RouteSubscriber.
 */

namespace Drupal\mailchimp_ecommerce_promo_code\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('mailchimp_ecommerce.sync_promotions')) {
      $route->setDefault('_form', '\Drupal\mailchimp_ecommerce_promo_code\Form\MailchimpEcommercePromoCodeSyncPromotions');
    }

  }

}
