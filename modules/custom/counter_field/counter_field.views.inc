<?php
/**
 * @file
 * Contains counter_field\counter_field.views.inc..
 * Provide a custom views field data that isn't tied to any other module. */
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Render\Markup;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\system\ActionConfigEntityInterface;
/**
 * Implements hook_views_data().
 */
function counter_field_views_data() {
  $data['views']['table']['group'] = t('Custom Global');
  $data['views']['table']['join'] = [
    // #global is a special flag which allows a table to appear all the time.
    '#global' => [],
  ];
  $data['views']['counter_views_field'] = [
    'title' => t('Counter views field'),
    'help' => t('My awesome custom views field plugin.'),
    'field' => [
      'id' => 'counter_views_field',
    ],
  ];
  return $data;
}