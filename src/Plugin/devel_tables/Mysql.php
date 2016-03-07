<?php

namespace Drupal\devel_tables\Plugin\devel_tables;

use Drupal\Core\Plugin\PluginBase;
use Drupal\devel_tables\Plugin\DevelTablesDriverInterface;

/**
 * devel_tables driver for mysql Drupal driver.
 *
 * @DevelTablesDriver(
 *   id = "mysql",
 *   title = @Translation("Devel_tables driver for Drupal mysql driver"),
 *   help = @Translation("Devel_tables driver for Drupal mysql driver."),
 * )
 */
class Mysql extends PluginBase implements DevelTablesDriverInterface {

  /**
   * @todo
   */
  public function getConnectionInfo(array $drupal_connection) {
    return [
      'dbname' => $drupal_connection['database'],
      'user' => $drupal_connection['username'],
      'password' => $drupal_connection['password'],
      'host' => $drupal_connection['host'],
      'port' => $drupal_connection['port'],
      'driver' => 'pdo_mysql',
      'charset' => 'utf8',
    ];
  }

  /**
   * @todo typehint
   */
  public function getExtraTableInfo($connection, $dbal_tables) {
    $recs = $connection->query("show table status")->fetchAll();
    $extra = [];
    foreach ($recs as $rec) {
      $extra[$rec['Name']] = $rec;
      $extra[$rec['Name']]['_rows'] = empty($rec['Rows']) ? NULL : $rec['Rows'];
      $extra[$rec['Name']]['_description'] = empty($rec['Comment']) ? t('*** No description available ***') : $rec['Comment'];
    }
    return $extra;
  }

}
