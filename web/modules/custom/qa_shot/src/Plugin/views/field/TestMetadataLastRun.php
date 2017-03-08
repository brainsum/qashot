<?php

namespace Drupal\qa_shot\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Render\Markup;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("test_metadata_last_run")
 */
class TestMetadataLastRun extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['stage'] = [
      'table' => 'qa_shot_test__metadata_last_run',
      'field' => 'metadata_last_run_stage',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $formState) {
    parent::buildOptionsForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if ($values->index == 0) {
      /** @var QAShotTestInterface $test */
      $metadata = $values->_entity->getLastRunMetadataValue();
      $serializedMetadata = json_encode($metadata);

      return $this->sanitizeValue($metadata);
    }

    return [
      // @todo: Create a twig to render it, or what?
    ];
  }

}
