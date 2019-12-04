<?php

namespace Drupal\Tests\mailchimp_ecommerce\Unit;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mailchimp_ecommerce\CartHandler;
use Drupal\mailchimp_ecommerce\MailchimpEcommerceHelper;
use Drupal\Tests\UnitTestCase;
use Mailchimp\MailchimpEcommerce;

require_once __DIR__ . '/../../require.php';

/**
 * Tests cart handler methods.
 *
 * This is an example unit test, created in the hopes that more will be added
 * in future work.
 *
 * @coversDefaultClass \Drupal\mailchimp_ecommerce\CartHandler
 *
 * @group mailchimp_ecommerce
 */
class CartHandlerTest extends UnitTestCase {

  /**
   * The cart handler.
   *
   * @var \Drupal\mailchimp_ecommerce\CartHandler
   */
  protected $handler;

  /**
   * A mock of the messenger service.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $messenger;

  /**
   * A mock of the \Drupal\mailchimp_ecommerce\MailchimpEcommerceHelper class.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $helper;

  /**
   * A mock of the \Mailchimp\MailchimpEcommerce class.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $mcEcommerce;

  /**
   * An array of errors that the messenger adds to when addError is called.
   *
   * @var array
   */
  protected $errors;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->mcEcommerce = $this->createMock(MailchimpEcommerce::class);
    $this->helper = $this->createMock(MailchimpEcommerceHelper::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->handler = new CartHandler($this->mcEcommerce, $this->helper, $this->messenger);

    $this->errors = [];
    $this->messenger->method('addError')->willReturnCallback(function ($error) {
      $this->errors[] = $error;
    });
  }

  /**
   * @covers ::getCart
   * @covers ::cartExists
   */
  public function testGetCart() {
    // Test positive case.
    $this->helper->expects($this->at(0))->method('getStoreId')->willReturn('my_store');
    $cart = new \stdClass();
    $cart->foo = 'bar';
    $this->mcEcommerce->expects($this->at(0))->method('getCart')->willReturn($cart);
    $this->assertTrue($this->handler->cartExists('my_cart'));
    // Test negative case.
    $this->helper->expects($this->at(0))->method('getStoreId')->willReturn('my_store');
    $this->mcEcommerce->expects($this->at(0))->method('getCart')->willReturn(NULL);
    $this->assertFalse($this->handler->cartExists('my_cart'));
    // Test error handling.
    $this->helper->expects($this->at(0))->method('getStoreId')->willReturn('');
    $this->handler->cartExists('my_cart');
    $this->assertContains('Cannot get the requested cart without a store ID.', $this->errors);
  }

  /**
   * @covers ::addOrUpdateCart
   */
  public function testAddOrUpdateCart() {
    // Test update case.
    $this->helper->method('getStoreId')->willReturn('my_store');
    $this->helper->method('validateCustomer')->willReturn(TRUE);
    $this->helper->method('getCampaignId')->willReturn(FALSE);
    $cart = new \stdClass();
    $cart->foo = 'bar';
    $this->mcEcommerce->expects($this->at(0))->method('getCart')->willReturn($cart);
    $this->mcEcommerce->expects($this->once())->method('updateCart');
    $this->handler->addOrUpdateCart('my_cart', [], []);
    // Test add case.
    $exception = new \Exception('', 404);
    $this->mcEcommerce->expects($this->at(0))->method('getCart')->will($this->throwException($exception));
    $this->mcEcommerce->expects($this->once())->method('addCart');
    $this->handler->addOrUpdateCart('my_cart', [], []);
  }

  /**
   * @covers ::deleteCart
   */
  public function testDeleteCart() {
    $this->helper->method('getStoreId')->willReturn('my_store');
    $this->mcEcommerce->expects($this->at(0))->method('deleteCart');
    $this->handler->deleteCart('my_cart');
    // Test ignored exception handling.
    $exception = new \Exception('Error!', 404);
    $this->mcEcommerce->expects($this->at(0))->method('deleteCart')->will($this->throwException($exception));
    $this->handler->deleteCart('my_cart');
    $this->assertEmpty($this->errors);
    // Test real exception handling.
    $exception = new \Exception('Error!', 500);
    $this->mcEcommerce->expects($this->at(0))->method('deleteCart')->will($this->throwException($exception));
    $this->handler->deleteCart('my_cart');
    $this->assertContains('Error!', $this->errors);
  }

  /**
   * @covers ::updateCartLine
   */
  public function testUpdateCartLine() {
    $this->helper->method('getStoreId')->willReturn('my_store');
    $this->mcEcommerce->expects($this->at(0))->method('updateCartLine');
    $this->handler->updateCartLine('my_cart', 1, []);
    // Test add case.
    $exception = new \Exception('', 404);
    $this->mcEcommerce->expects($this->at(0))->method('updateCartLine')->will($this->throwException($exception));
    $this->mcEcommerce->expects($this->once())->method('addCartLine');
    $this->handler->updateCartLine('my_cart', 1, []);
    // Test error handling.
    $exception = new \Exception('Error!', 500);
    $this->mcEcommerce->expects($this->at(0))->method('updateCartLine')->will($this->throwException($exception));
    $this->handler->updateCartLine('my_cart', 1, []);
    $this->assertContains('Error!', $this->errors);
  }

  /**
   * @covers ::deleteCartLine
   */
  public function testDeleteCartLine() {
    $this->helper->method('getStoreId')->willReturn('my_store');
    $this->mcEcommerce->expects($this->at(0))->method('deleteCartLine');
    $this->handler->deleteCartLine('my_cart', 1);
    // Test error handling.
    $exception = new \Exception('Error!');
    $this->mcEcommerce->expects($this->at(0))->method('deleteCartLine')->will($this->throwException($exception));
    $this->handler->deleteCartLine('my_cart', 1, []);
    $this->assertContains('Error!', $this->errors);
  }

}
