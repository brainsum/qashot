<?php

namespace Drupal\Tests\qa_shot_rest_api\Unit;

/**
 * Class ExampleTest
 *
 * @package Drupal\Tests\qa_shot_rest_api\Unit
 *
 * @group Example
 */
class ExampleTest extends \PHPUnit_Framework_TestCase {

  public static function addProvider() {
    return array(
      array(0, 0, 0),
      array(0, 1, 1),
    );
  }

  /**
   * @dataProvider addProvider
   */
  public function testAdd($a, $b, $c) {
    $this->assertEquals($c, $a + $b);
  }

}
