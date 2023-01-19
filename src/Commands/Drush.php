<?php

namespace Drupal\civicrm_group_roles\Commands;

use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\civicrm_group_roles\Commands
 */
class Drush extends DrushCommands {

  /**
   * Drush command for CiviCRM Group Roles Sync..
   *
   * @param array $options
   *   Array containing CLI options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command civicrm-group-role-sync:civicrm-group-role-sync
   * @aliases cgrs
   * @usage cgrs
   * @options uid Optional User ID.
   * @options contact_id Optional Contact ID.
   */
  public function drushcgrs(array $options = ['uid' => NULL, 'contact_id' => NULL]) {
    /** @var \Drupal\civicrm_group_roles\CivicrmGroupRoles $civicrmGroupRoles */
    $civicrmGroupRoles = \Drupal::service('civicrm_group_roles');

    if (isset($options['uid']) && $uid = $options['uid']) {
      $storage = \Drupal::entityTypeManager()->getStorage('user');
      if (!$account = $storage->load($uid)) {
        $this->output()
          ->writeln(print_r(dt('Unable to load user ID @uid.', ['@uid' => $uid]), TRUE));
      }
      else {
        $civicrmGroupRoles->userAddGroups($account);
        $civicrmGroupRoles->syncRoles($account);
        $this->output()
          ->writeln(print_r(dt('Successfully synced user ID @uid.', ['@uid' => $uid]), TRUE));
      }
    }
    else {
      if (isset($options['contact_id']) && $contact_id = $options['contact_id']) {
        if (!$account = $civicrmGroupRoles->getContactUser($contact_id)) {
          $this->output()
            ->writeln(print_r(dt('Unable to load user for contact ID @cid.', ['@cid' => $contact_id]), TRUE));
        }
        else {
          $civicrmGroupRoles->userAddGroups($account);
          $civicrmGroupRoles->syncRoles($account);
          $this->output()
            ->writeln(print_r(dt('Successfully synced contact ID @cid.', ['@cid' => $contact_id]), TRUE));
        }
      }
      else {
        $batch = \Drupal::service('civicrm_group_roles.batch.sync')->getBatch();
        $batch['progressive'] = FALSE;
        batch_set($batch);
        drush_backend_batch_process();
        $this->output()
          ->writeln(print_r("Successfully synced", TRUE));
      }
    }

  }

}
