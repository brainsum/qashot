<?php

namespace Drupal\qa_shot\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the QAShot Test type entity.
 *
 * @ConfigEntityType(
 *   id = "qa_shot_test_type",
 *   label = @Translation("QAShot Test type"),
 *   handlers = {
 *     "list_builder" = "Drupal\qa_shot\QAShotTestTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qa_shot\Form\QAShotTestTypeForm",
 *       "edit" = "Drupal\qa_shot\Form\QAShotTestTypeForm",
 *       "delete" = "Drupal\qa_shot\Form\QAShotTestTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qa_shot\QAShotTestTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "qa_shot_test_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "qa_shot_test",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/qa_shot_test_type/add",
 *     "edit-form" = "/admin/structure/qa_shot_test_type/{qa_shot_test_type}/edit",
 *     "delete-form" = "/admin/structure/qa_shot_test_type/{qa_shot_test_type}/delete",
 *     "collection" = "/admin/structure/qa_shot_test_type"
 *   }
 * )
 */
class QAShotTestType extends ConfigEntityBundleBase implements QAShotTestTypeInterface {

  /**
   * The QAShot Test type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The QAShot Test type label.
   *
   * @var string
   */
  protected $label;

}
