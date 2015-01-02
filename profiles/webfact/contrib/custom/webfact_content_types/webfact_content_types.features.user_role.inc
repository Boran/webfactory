<?php
/**
 * @file
 * webfact_content_types.features.user_role.inc
 */

/**
 * Implements hook_user_default_roles().
 */
function webfact_content_types_user_default_roles() {
  $roles = array();

  // Exported role: Site owner.
  $roles['Site owner'] = array(
    'name' => 'Site owner',
    'weight' => 3,
  );

  return $roles;
}
