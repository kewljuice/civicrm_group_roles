<?php

namespace Drupal\civicrm_group_roles\Batch;

use Drupal;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Entity\User;

/**
 * Class Sync.
 */
class Sync {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Sync constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database.
   */
  public function __construct(TranslationInterface $stringTranslation, Connection $connection) {
    $this->stringTranslation = $stringTranslation;
    $this->connection = $connection;
  }

  /**
   * Get the batch.
   *
   * @return array
   *   A batch API array for syncing user groups and roles.
   */
  public function getBatch() {
    $batch = [
      'title' => $this->t('Updating Users...'),
      'operations' => [],
      'init_message' => $this->t('Starting Update'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('An error occurred during processing'),
      'finished' => [$this, 'finished'],
    ];

    $uids = $this->getDatabase()
      ->query('SELECT uid FROM {users} WHERE uid > 0')
      ->fetchCol();
    $batch['operations'][] = [[$this, 'process'], [$uids]];

    return $batch;
  }

  /**
   * Get the database connection.
   *
   * This is called directly from the Drupal object to avoid dealing with
   * serialization.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function getDatabase() {
    return $this->connection;
  }

  /**
   * Batch API process callback.
   *
   * @param array $uids
   *   User IDs to process.
   * @param mixed $context
   *   Batch API context data.
   */
  public function process(array $uids, &$context) {
    if (!isset($context['sandbox']['uids'])) {
      $context['sandbox']['uids'] = $uids;
      $context['sandbox']['max'] = count($uids);
      $context['results']['processed'] = 0;
    }

    $uid = array_shift($context['sandbox']['uids']);
    $account = User::load($uid);

    $this->getCivicrmGroupRoles()->userAddGroups($account);
    $this->getCivicrmGroupRoles()->syncRoles($account);
    $context['results']['processed']++;

    if (count($context['sandbox']['uids']) > 0) {
      $context['finished'] = 1 - (count($context['sandbox']['uids']) / $context['sandbox']['max']);
    }
  }

  /**
   * Get CiviCRM group roles service.
   *
   * This is called directly from the Drupal object to avoid dealing with
   * serialization.
   *
   * @return \Drupal\civicrm_group_roles\CivicrmGroupRoles
   *   The CiviCRM group roles service.
   */
  protected function getCivicrmGroupRoles() {
    return Drupal::service('civicrm_group_roles');
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Batch API success indicator.
   * @param array $results
   *   Batch API results array.
   */
  public function finished($success, array $results) {
    if ($success) {
      $message = $this->stringTranslation->formatPlural($results['processed'], 'One user processed.', '@count users processed.');
      Drupal::messenger()->addMessage($message);
    }
    else {
      $message = $this->t('Encountered errors while performing sync.');
      Drupal::messenger()->addMessage($message, 'error');
    }

  }

}
