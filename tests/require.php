<?php

/**
 * @file
 * Contains includes for unit tests.
 */

if (!class_exists('Mailchimp\Mailchimp')) {
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/Mailchimp.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpAPIException.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpAutomations.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpCampaigns.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpConnectedSites.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpEcommerce.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpLists.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpReports.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/MailchimpTemplates.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/http/MailchimpHttpClientInterface.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/http/MailchimpCurlHttpClient.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/http/MailchimpGuzzleHttpClient.php';
  include_once __DIR__ . '/../../mailchimp/lib/mailchimp-api-php/src/Mailchimp.php';
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/Client.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/Mailchimp.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpAutomations.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpCampaigns.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpEcommerce.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpLists.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpReports.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpTemplates.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpTestHttpClient.php";
  include_once __DIR__ . "/../../mailchimp/lib/mailchimp-api-php/tests/src/MailchimpTestHttpResponse.php";
}
