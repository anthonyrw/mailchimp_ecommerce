<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\mailchimp_ecommerce\PromoHandler;

/**
 * Order handler.
 */
class OrderHandler implements OrderHandlerInterface {

  /**
   * @inheritdoc
   */
  public function getOrder($order_id) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot get an order without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $order = $mc_ecommerce->getOrder($store_id, $order_id);
      return $order;
    }
    catch (\Exception $e) {
      if($e->getCode() == 404) {
        return NULL;
      }
      else {
        mailchimp_ecommerce_log_error_message('Unable to get order: ' . $e->getMessage());
        drupal_set_message($e->getMessage(), 'error');
        return NULL;
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function addOrder($order_id, array $customer, array $order) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot add an order without a store ID.');
      }
      if (mailchimp_ecommerce_validate_customer($customer)) {
        // A user not existing in the store's Mailchimp list is not an error, so don't throw an exception.
//        /** @var \Drupal\mailchimp_ecommerce\CustomerHandler $customer_handler */
//        $customer_handler = \Drupal::service('mailchimp_ecommerce.customer_handler');
//        $customer_handler->addOrUpdateCustomer($customer);
        unset($customer['email_address']);
      }

      // Get the Mailchimp campaign ID, if available.
      $campaign_id = mailchimp_ecommerce_get_campaign_id();
      if (!empty($campaign_id)) {
        $order['campaign_id'] = $campaign_id;
        $order['landing_site'] = isset($_SESSION['mc_landing_site']) ? $_SESSION['mc_landing_site'] : '';
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->addOrder($store_id, $order_id, $customer, $order);
    }
    catch (\Exception $e) {
      mailchimp_ecommerce_log_error_message('Unable to add an order: ' . $e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function updateOrder($order_id, array $order) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot update an order without a store ID.');
      }
      $customer = $order['customer'];
      if (mailchimp_ecommerce_validate_customer($customer)) {
        unset($order['customer']['email_address']);
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->updateOrder($store_id, $order_id, $order);
    }
    catch (\Exception $e) {
      if($e->getCode() == 404) {
        try {
          $this->addOrder($order_id, $order['customer'], $order);
        } catch(\Exception $e) {
          mailchimp_ecommerce_log_error_message('Order update failed; attempted to create order instead. This also failed. '. $e->getMessage());
        }
      }
      else {
        mailchimp_ecommerce_log_error_message('Unable to update an order: ' . $e->getMessage());
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function buildOrder(Order $order, array $customer) {
    $order_items = $order->getItems();
    $lines = [];

    /** @var OrderItem $order_item */
    foreach ($order_items as $order_item) {
      $line = [
        'id' => $order_item->id(),
        'product_id' => $order_item->getPurchasedEntity()->getProductId(),
        // TODO: Figure out how to differentiate between product and variant ID here.
        'product_variant_id' => $order_item->getPurchasedEntityId(),
        'quantity' => (int) $order_item->getQuantity(),
        'price' => $order_item->getUnitPrice()->getNumber(),
      ];

      $lines[] = $line;
    }

    $order_data = [
      'customer' => $customer,
      'updated_at_foreign' => date('c'),
      'lines' => $lines,
    ];

    if(!empty($order->getBillingProfile())) {
      $order_data['billing_address'] = $this->translateAddress($order->getBillingProfile());
    }

    if( $order->hasField('shipments') && !$order->get('shipments')->isEmpty() ) {
      $shipment = $order->get('shipments')->referencedEntities()[0];
      $order_data['shipping_address'] = $this->translateAddress($shipment->getShippingProfile());
    }

    if (!empty($order->getTotalPrice())) {
      $order_data['currency_code'] = $order->getTotalPrice()->getCurrencyCode();
      $order_data['order_total'] = $order->getTotalPrice()->getNumber();
    }

    if(!empty($order->getAdjustments())) {
      $adjustments = [];
      $tax_total = 0;
      $shipping_total = 0;
      $discount_total = 0;

      /** @var \Drupal\commerce_order\Adjustment $adjustment */
      foreach ($order->collectAdjustments(['tax']) as $adjustment) {
        $tax_total += floatval($adjustment->getAmount()->getNumber());
      }
      foreach ($order->collectAdjustments(['shipping']) as $adjustment) {
        $shipping_total += floatval($adjustment->getAmount()->getNumber());
      }
      foreach ($order->collectAdjustments(['promotion', 'shipping_promotion']) as $adjustment) {
        $discount_total += floatval($adjustment->getAmount()->getNumber());
        $adjustments[$adjustment->getSourceId()] = $adjustment->getAmount()->getNumber();
      }

      $order_data['tax_total'] = strval($tax_total);
      $order_data['shipping_total'] = strval($shipping_total);
      $order_data['discount_total'] = strval($discount_total);

      foreach ($order->get('coupons') as $coupon) {
        if( !empty($adjustments) ) {
          try {
            $coupon_id = $coupon->get('target_id')->getCastedValue();
            $promotion_id = Coupon::load($coupon_id)->getPromotionId();
            $promo_handler = new PromoHandler();
            $order_data['promos'][] = [
              'code' => $promo_handler->getPromoCode($promotion_id, $coupon_id)->code,
              'amount_discounted' => $adjustments[$promotion_id],
              'type' => $promo_handler->getPromoRule($promotion_id)->type,
            ];
          } catch (\Exception $e) {
            mailchimp_ecommerce_log_error_message('A promotion was not properly synced to Mailchimp. ' . $e->getMessage());
          }
        }
      }
    }

    return $order_data;
  }

  /**
   * @inheritdoc
   */
  public function buildProduct(OrderItem $order_item) {

    $product = [
      'id' => $order_item->id(),
      'product_id' => $order_item->getPurchasedEntity()->getProductId(),
      // TODO: Figure out how to differentiate between product and variant ID here.
      'product_variant_id' => $order_item->getPurchasedEntityId(),
      'quantity' => (int) $order_item->getQuantity(),
      'price' => $order_item->getUnitPrice()->getNumber(),
    ];

    return $product;
  }

  /**
   * @inheritdoc
   */
  function buildUberOrder(\Drupal\uc_order\Entity\Order $order) {
    $currency_code = $order->getCurrency();
    $order_total = '';
    $lines = [];
    $mc_billing_address = [];

    //TODO: Refactor this into the customer handler.
    $customer_handler = new customerHandler(\Drupal::database());

    $billing_address = $order->getAddress('billing');
    if ($billing_address->getFirstName() && $billing_address->getLastName()) {
      $mc_billing_address = [
        'name' => $billing_address->getFirstName() . ' ' . $billing_address->getLastName(),
        'company' => $billing_address->getCompany(),
        'address1' => $billing_address->getStreet1(),
        'address2' => $billing_address->getStreet2(),
        'city' => $billing_address->getCity(),
        'province_code' => $billing_address->getZone(),
        'postal_code' => $billing_address->getPostalCode(),
        'country_code' => $billing_address->getCountry(),
      ];
    }
    foreach ($mc_billing_address as $key => $value) {
      if ($value === null) {
        unset($mc_billing_address[$key]);
      }
    }
    $order_total = $order->getTotal();
    $products = $order->products;

    if (!empty($products)) {
      foreach ($products as $product) {
        $line = [
          'id' => $product->nid->target_id,
          'product_id' => $product->nid->target_id,
          'product_variant_id' => $product->nid->target_id,
          'quantity' => (int) $product->qty->value,
          'price' => $product->price->value,
        ];

        $lines[] = $line;
      }
    }

    $customer_id = $customer_handler->loadCustomerId($order->mail);

    $list_id = mailchimp_ecommerce_get_list_id();
    // Pull member information to get member status.
    $memberinfo = mailchimp_get_memberinfo($list_id, $order->getEmail(), TRUE);

    $opt_in_status = (isset($memberinfo->status) && ($memberinfo->status == 'subscribed')) ? TRUE : FALSE;

    $customer = [
      'id' => $customer_id,
      'email_address' => $order->getEmail(),
      'opt_in_status' => $opt_in_status,
      'first_name' => $billing_address->getFirstName(),
      'last_name' => $billing_address->getlastName(),
      'address' => $mc_billing_address,
    ];

    foreach ($customer as $key => $value) {
      if ($value === null) {
        unset($customer[$key]);
      }
    }
    // TODO: END Refactor

    $order_data = [
      'customer' => $customer,
      'currency_code' => $currency_code,
      'order_total' => $order_total,
      'billing_address' => $mc_billing_address,
      'processed_at_foreign' => date('c'),
      'lines' => $lines,
    ];
    foreach ($order_data as $key => $value) {
      if ($value === null) {
        unset($order_data[$key]);
      }
    }

    return ['customer' => $customer, 'order_data' => $order_data];
  }

  // CUSTOM ORDER HANDLER FUNCTIONS

  /**
   * Accepts a Profile as input and converts into an array that can be used by
   * the Mailchimp API for billing and shipping addresses
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   * @return array $address
   */
  private function translateAddress($profile) {
    try {
      /** @var AddressItem $address */
      $address = $profile->get('address')->first();
      $mc_address = [];
      if(!empty($address->getGivenName()) && !empty($address->getFamilyName())){
        $mc_address['name'] = ($address->getGivenName() . ' ' . $address->getFamilyName());
      }
      if(!empty($address->getAddressLine1())){
        $mc_address['address1'] = $address->getAddressLine1();
      }
      if(!empty($address->getAddressLine2())){
        $mc_address['address2'] = $address->getAddressLine2();
      }
      if(!empty($address->getLocality())){
        $mc_address['city'] = $address->getLocality();
      }
      if(!empty($address->getAdministrativeArea())){
        $mc_address['province_code'] = $address->getAdministrativeArea();
      }
      if(!empty($address->getPostalCode())){
        $mc_address['postal_code'] = $address->getPostalCode();
      }
      if(!empty($address->getCountryCode())){
        $mc_address['country_code'] = $address->getCountryCode();
      }
      if(!empty($address->getOrganization())){
        $mc_address['company'] = $address->getOrganization();
      }
      $phone = $this->getTelephone($profile);
      if(!empty($phone)){
        $mc_address['phone'] = $phone;
      }
      return $mc_address;
    } catch (\Exception $e) {
      \Drupal::logger('mailchimp_ecommerce')
        ->error('Attempt to translate profile into mailchimp array failed: ' . $e->getMessage());
    }
  }

  /**
   * @param $profile
   *
   * @return null|string
   */
  private function getTelephone($profile) {
    $telephone = NULL;
    $field = \Drupal::config('mailchimp_ecommerce.settings')->get('telephone');
    $telephone = strval($profile->get($field)->value);
    return $telephone;
  }
}
