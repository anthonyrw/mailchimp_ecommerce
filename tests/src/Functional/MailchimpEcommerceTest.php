<?php

namespace Drupal\Tests\mailchimp_ecommerce\Functional;

use Drupal\Tests\mailchimp\Functional\FunctionalMailchimpTestBase;

/**
 * Tests for Mailchimp eCommerce core integration.
 *
 * @group mailchimp_ecommerce
 */
class MailchimpEcommerceTest extends FunctionalMailchimpTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $override = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['mailchimp_ecommerce'];

  /**
   * Tests the base Mailchimp configuration form.
   */
  public function testConfigurationForm() {
    \Drupal::configFactory()->getEditable('mailchimp.settings')
      ->set('api_key', 'TEST_KEY')
      ->set('test_mode', TRUE)
      ->save();
    $assert_session = $this->assertSession();
    $this->drupalGet('/admin/config/services/mailchimp/ecommerce');
    $this->assertResponse(403);
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/mailchimp/ecommerce');
    $this->assertResponse(200);
    $this->submitForm([
      'mailchimp_ecommerce_store_name' => 'my_store',
      'mailchimp_ecommerce_list_id' => '57afe96172',
      'mailchimp_ecommerce_currency' => 'USD',
    ], 'Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved');
  }

}
