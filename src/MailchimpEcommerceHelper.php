<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Mailchimp\MailchimpCampaigns;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MailchimpEcommerceHelper.
 *
 * Contains shared helper methods that various handlers use.
 */
class MailchimpEcommerceHelper implements ContainerInjectionInterface {

  /**
   * The campaign service.
   *
   * @var \Mailchimp\MailchimpCampaigns
   */
  protected $mcCampaigns;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The mailchimp_ecommerce config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * MailchimpEcommerceHelper constructor.
   *
   * @param \Mailchimp\MailchimpCampaigns $mc_campaigns
   *   The campaign service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The mailchimp_ecommerce config.
   */
  public function __construct(MailchimpCampaigns $mc_campaigns, MessengerInterface $messenger, Request $request, ImmutableConfig $config) {
    $this->mcCampaigns = $mc_campaigns;
    $this->messenger = $messenger;
    $this->request = $request;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      \mailchimp_get_api_object('MailchimpCampaigns'),
      $container->get('messenger'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory')->get('mailchimp_ecommerce.settings')
    );
  }

  /**
   * Determines if customer data is valid.
   *
   * @param array $customer
   *   Array of customer data.
   *
   * @return bool
   *   TRUE if customer data is valid.
   */
  public function validateCustomer(array $customer) {
    return (isset($customer['id']) && !empty($customer['id']));
  }

  /**
   * Gets the campaign ID from the current user's session.
   *
   * @return string
   *   The campaign ID.
   */
  public function getCampaignId() {
    $session_campaign = $this->request->getSession()->get('mc_cid', '');
    $campaign_id = '';

    // Check to see if this is a valid Mailchimp campaign.
    try {
      if (!empty($session_campaign)) {
        $campaign = $this->mcCampaigns->getCampaign($session_campaign);
        $campaign_id = $campaign->id;
      }
    }
    catch (\Exception $e) {
      if ($e->getCode() == 404) {
        // Campaign doesn't exist; no need to log an error.
      }
      else {
        /* mailchimp_ecommerce_log_error_message('Unable to get campaign: ' . $e->getMessage()); */
        $this->messenger->addError($e->getMessage());
      }
    }

    return $campaign_id;
  }

  /**
   * Returns currency codes from the xml file.
   *
   * This is used if Drupal Commerce is not available.
   *
   * @return array
   *   Array of currency codes.
   */
  public function getCurrencyCodes() {
    $currencyfile = drupal_get_path('module', 'mailchimp_ecommerce') . '/' . 'currency-codes-iso4217.xml';
    $currencydata = simplexml_load_file($currencyfile);
    $json_string = json_encode($currencydata);
    $result_array = json_decode($json_string, TRUE);
    $currencycodes = [];
    foreach ($result_array['CcyTbl']['CcyNtry'] as $item) {
      if (!empty($item['Ccy'])) {
        $currencycodes[$item['Ccy']] = $item['CcyNm'] . ' (' . $item['Ccy'] . ')';
      }
    }
    return $currencycodes;
  }

  /**
   * Gets the store ID.
   *
   * @return string
   *   The store ID.
   */
  public function getStoreId() {
    return $this->config->get('mailchimp_ecommerce_store_id');
  }

  /**
   * Determines if a Mailchimp eCommerce integration is enabled.
   *
   * @return bool
   *   TRUE if an integration is enabled.
   */
  public function isEnabled() {
    return ($this->config->get('mailchimp_ecommerce_integration') !== '');
  }

}
