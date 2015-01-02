<?php
/**
 * @file
 * webfact_content_types.features.menu_links.inc
 */

/**
 * Implements hook_menu_default_menu_links().
 */
function webfact_content_types_menu_default_menu_links() {
  $menu_links = array();

  // Exported menu link: main-menu_templates:templates
  $menu_links['main-menu_templates:templates'] = array(
    'menu_name' => 'main-menu',
    'link_path' => 'templates',
    'router_path' => 'templates',
    'link_title' => 'Templates',
    'options' => array(
      'identifier' => 'main-menu_templates:templates',
    ),
    'module' => 'system',
    'hidden' => 0,
    'external' => 0,
    'has_children' => 0,
    'expanded' => 0,
    'weight' => -48,
    'customized' => 1,
  );
  // Exported menu link: main-menu_websites:websites
  $menu_links['main-menu_websites:websites'] = array(
    'menu_name' => 'main-menu',
    'link_path' => 'websites',
    'router_path' => 'websites',
    'link_title' => 'Websites',
    'options' => array(
      'identifier' => 'main-menu_websites:websites',
    ),
    'module' => 'system',
    'hidden' => 0,
    'external' => 0,
    'has_children' => 0,
    'expanded' => 0,
    'weight' => -49,
    'customized' => 1,
  );
  // Translatables
  // Included for use with string extractors like potx.
  t('Templates');
  t('Websites');


  return $menu_links;
}
