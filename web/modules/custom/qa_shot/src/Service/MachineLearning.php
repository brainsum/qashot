<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\StreamWrapper\PrivateStream;
use Phpml\Dataset\ArrayDataset;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;

/**
 * Class MachineLearning.
 *
 * @package Drupal\qa_shot\Service
 */
class MachineLearning {

  /**
   * Sandbox function to try the ML library.
   */
  public function sandbox() {
    /*
     * Copy this into /devel/php
     *
     * \Drupal::service('qa_shot.machine_learning')->sandbox();
     *
     * Documentation: http://php-ml.readthedocs.io/en/latest/
     */

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->load(5);
    $metadata = $entity->getLifetimeMetadataValue();

    $trainingData = $metadata;
    unset($trainingData[0]);

    $samples = [];
    $targets = [];

    foreach ($trainingData as $data) {
      $samples[] = [
        (int) $data['viewport_count'],
        (int) $data['scenario_count'],
      ];

      $targets[] = (float) $data['duration'];
    }

    $trainingDataset = new ArrayDataset($samples, $targets);

    $predictFor = [
      (int) $metadata[0]['viewport_count'],
      (int) $metadata[0]['scenario_count'],
    ];

    $tmpFilePath = \Drupal::service('file_system')->realpath(PrivateStream::basePath()) . '/qa_test_data/' . $entity->id() . '/tmp/';
    $regression = new SVR(Kernel::LINEAR, 3, 0.1, 1.0, NULL, 0.0, 0.001, 100, TRUE, $tmpFilePath);
    // @note: This is not there by default.
    /*
     * @code
     * public function setVarPath($path) {
     *   $this->varPath = $path;
     * }
     * @code
     *
     * Add to this:
     * ../vendor/php-ai/php-ml/src/Phpml/SupportVectorMachine/SupportVectorMachine.php
     */
    $regression->setVarPath($tmpFilePath);
    $regression->train($samples, $targets);
    $result = $regression->predict($predictFor);

    dpm($metadata);

    dpm('prediction', $result, 'actual', (float) $metadata[0]['duration']);

  }

  public function sandboxAgain() {
    $samples = [[73676, 1996], [77006, 1998], [10565, 2000], [146088, 1995], [15000, 2001], [65940, 2000], [9300, 2000], [93739, 1996], [153260, 1994], [17764, 2002], [57000, 1998], [15000, 2000]];
    $targets = [2000, 2750, 15500, 960, 4400, 8800, 7100, 2550, 1025, 5900, 4600, 4400];

    dpm($samples);
    dpm($targets);

    $regression = new SVR(Kernel::LINEAR);
    $regression->train($samples, $targets);
    $result = $regression->predict([60000, 1996]);
    dpm($result);
  }

}
