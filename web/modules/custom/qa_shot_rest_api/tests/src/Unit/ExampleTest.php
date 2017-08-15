<?php

namespace Drupal\Tests\qa_shot_rest_api\Unit;

/**
 * Class ExampleTest.
 *
 * @package Drupal\Tests\qa_shot_rest_api\Unit
 *
 * @group Example
 */
class ExampleTest extends \PHPUnit_Framework_TestCase {

  /**
   * Data provider.
   *
   * @return array
   *   The provided data.
   */
  public static function addProvider(): array {
    return [
      [0, 0, 0],
      [0, 1, 1],
    ];
  }

  /**
   * Example test for addition.
   *
   * @param int $a
   * @param int $b
   * @param int $c
   *
   * @dataProvider addProvider
   */
  public function testAdd($a, $b, $c) {
    $this->assertEquals($c, $a + $b);
  }

}
