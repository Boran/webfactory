<?php
/**
 * @file
 * webfact_content_types.features.inc
 */

/**
 * Implements hook_ctools_plugin_api().
 */
function webfact_content_types_ctools_plugin_api($module = NULL, $api = NULL) {
  if ($module == "field_group" && $api == "field_group") {
    return array("version" => "1");
  }
  if ($module == "strongarm" && $api == "strongarm") {
    return array("version" => "1");
  }
}

/**
 * Implements hook_views_api().
 */
function webfact_content_types_views_api($module = NULL, $api = NULL) {
  return array("api" => "3.0");
}

/**
 * Implements hook_node_info().
 */
function webfact_content_types_node_info() {
  $items = array(
    'template' => array(
      'name' => t('Template'),
      'base' => 'node_content',
      'description' => t('Website template specifications: docker image, volumes and environment variables.
Security warning: only give trusted docker server administrators access to create/modify templates, as templates could affect the security of the docker server and all containers.'),
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
    'website' => array(
      'name' => t('Website'),
      'base' => 'node_content',
      'description' => t('Docker container (website) metadata'),
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
  );
  drupal_alter('node_info', $items);
  return $items;
}
