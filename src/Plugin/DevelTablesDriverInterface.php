<?php

namespace Drupal\devel_tables\Plugin;

/**
 * Provides an interface defining a devel_tables driver.
 */
interface DevelTablesDriverInterface {

  /**
   * @todo
   */
  public function getConnectionInfo(array $drupal_connection_info);

  /**
   * @todo
   */
  public function getExtraTableInfo($connection, $dbal_tables);

}
