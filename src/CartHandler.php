<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Mailchimp\MailchimpEcommerce;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cart handler.
 */
class CartHandler implements CartHandlerInterface, ContainerInjectionInterface {

  /**
   * The ecommerce service.
   *
   * @var \Mailchimp\MailchimpEcommerce
   */
  protected $mcEcommerce;

  /**
   * The ecommerce helper.
   *
   * @var \Drupal\mailchimp_ecommerce\MailchimpEcommerceHelper
   */
  protected $helper;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  public function __construct(MailchimpEcommerce $mc_ecommerce, MailchimpEcommerceHelper $helper, MessengerInterface $messenger) {
    $this->mcEcommerce = $mc_ecommerce;
    $this->helper = $helper;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      \mailchimp_get_api_object('MailchimpEcommerce'),
      $container->get('mailchimp_ecommerce.helper'),
      $container->get('messenger')
    );
  }

  /**
   * @inheritdoc
   */
  public function cartExists($cart_id) {
    return (!empty($this->getCart($cart_id)));
  }

  /**
   * @inheritdoc
   */
  public function getCart($cart_id) {
    $cart = NULL;

    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot get the requested cart without a store ID.');
      }

      try {
        $cart = $this->mcEcommerce->getCart($store_id, $cart_id);
      }
      catch (\Exception $e) {
        if ($e->getCode() == 404) {
          // Cart doesn't exist.
        }
        else {
          // An actual error occurred; pass on the exception.
          throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
      }
    }
    catch (\Exception $e) {
      //mailchimp_ecommerce_log_error_message('Unable to get the requested cart: ' . $e->getMessage());
      $this->messenger->addError($e->getMessage());
    }

    return $cart;
  }

  /**
   * @inheritdoc
   */
  public function addOrUpdateCart($cart_id, array $customer, array $cart) {
    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a cart without a store ID.');
      }
      if (!$this->helper->validateCustomer($customer)) {
        // A user not existing in the store's Mailchimp list/audience is not an error, so
        // don't throw an exception.
        return;
      }
      else {
        // "carts for existing customers should only include the id parameter in
        // the customer object body"
        // @see https://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/carts/
        $customer = [
          'id' => $customer['id'],
        ];
      }

      // Get the Mailchimp campaign ID, if available.
      $campaign_id = $this->helper->getCampaignId();
      if (!empty($campaign_id)) {
        $cart['campaign_id'] = $campaign_id;
        $cart['landing_site'] = isset($_SESSION['mc_landing_site']) ? $_SESSION['mc_landing_site'] : '';
      }

      try {
        if (!empty($this->mcEcommerce->getCart($store_id, $cart_id))) {
          $this->mcEcommerce->updateCart($store_id, $cart_id, $customer, $cart);
        }
      }
      catch (\Exception $e) {
        if ($e->getCode() == 404) {
          // Cart doesn't exist; add a new cart.
          $this->mcEcommerce->addCart($store_id, $cart_id, $customer, $cart);
        }
        else {
          // An actual error occurred; pass on the exception.
          throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
      }
    }
    catch (\Exception $e) {
      //mailchimp_ecommerce_log_error_message('Unable to add a cart: ' . $e->getMessage());
      $this->messenger->addError($e->getMessage());
    }
  }

  /**
   * @inheritdoc
   */
  public function deleteCart($cart_id) {
    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot delete a cart without a store ID.');
      }
      $this->mcEcommerce->deleteCart($store_id, $cart_id);
    }
    catch (\Exception $e) {
      if ($e->getCode() == 404) {
        // Cart doesn't exist; no need to log an error.
      }
      else {
        //mailchimp_ecommerce_log_error_message('Unable to delete a cart: ' . $e->getMessage());
        $this->messenger->addError($e->getMessage());
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function addCartLine($cart_id, $line_id, $product) {
    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a cart line without a store ID.');
      }

      $this->mcEcommerce->addCartLine($store_id, $cart_id, $line_id, $product);
    }
    catch (\Exception $e) {
      //mailchimp_ecommerce_log_error_message('Unable to add a cart line: ' . $e->getMessage());
      $this->messenger->addError($e->getMessage());
    }
  }

  /**
   * @inheritdoc
   */
  public function updateCartLine($cart_id, $line_id, $product) {
    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot update a cart line without a store ID.');
      }

      $this->mcEcommerce->updateCartLine($store_id, $cart_id, $line_id, $product);
    }
    catch (\Exception $e) {
      if ($e->getCode() == 404) {
        $this->mcEcommerce->addCartLine($store_id, $cart_id, $line_id, $product);
      }
      else {
        //mailchimp_ecommerce_log_error_message('Unable to update a cart line: ' . $e->getMessage());
        $this->messenger->addError($e->getMessage());
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function deleteCartLine($cart_id, $line_id) {
    try {
      $store_id = $this->helper->getStoreId();
      if (empty($store_id)) {
        throw new \Exception('Cannot delete a cart line without a store ID.');
      }

      $this->mcEcommerce->deleteCartLine($store_id, $cart_id, $line_id);
    }
    catch (\Exception $e) {
      //mailchimp_ecommerce_log_error_message('Unable to delete a cart line: ' . $e->getMessage());
      $this->messenger->addError($e->getMessage());
    }
  }

}
