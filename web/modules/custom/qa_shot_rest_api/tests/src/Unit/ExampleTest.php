<?php

namespace Drupal\Tests\qa_shot_rest_api\Unit;

use PHPUnit_Framework_TestCase;

/**
 * Class ExampleTest.
 *
 * @package Drupal\Tests\qa_shot_rest_api\Unit
 *
 * @group Example
 */
class ExampleTest extends PHPUnit_Framework_TestCase {

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
   *   First number.
   * @param int $b
   *   Second number.
   * @param int $c
   *   Expected sum.
   *
   * @dataProvider addProvider
   */
  public function testAdd($a, $b, $c): void {
    $this->assertEquals($c, $a + $b);
  }

}
