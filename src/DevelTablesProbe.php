<?php

/**
 * @file
 * Contains \Drupal\devel_tables\DevelTablesProbe.
 */

namespace Drupal\devel_tables;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

/**
 * @todo
 */
class DevelTablesProbe {

  protected $connectionType;
  protected $connectionKey;
  protected $connection;
  protected $develTablesDriver;

  public function connectDrupalDb($connection_key = 'default') {
    $this->connectionType = 'drupal';
    $this->connectionKey = $connection_key;

    // Get Drupal connection info.
    $drupal_connection = Database::getConnectionInfo($connection_key)['default']; // @todo allow selecting replicas?

    // Get translated DBAL connection info from the mapper plugin.
    $this->develTablesDriver = \Drupal::service('plugin.manager.devel_tables.driver')->createInstance($drupal_connection['driver']);
    $connection_parms = $this->develTablesDriver->getConnectionInfo($drupal_connection);

    // Connect to the database via DBAL.
    $this->connection = DriverManager::getConnection($connection_parms, new Configuration());
    return $this;
  }

  public function getTables() {
    if ($cache = \Drupal::cache()->get("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList")) {
      $tables = $cache->data;
    }
    else {
      $tables = $this->tableDataCollector();
      \Drupal::cache()->set("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList", $tables, Cache::PERMANENT); // @todo temporary
    }
    return $tables;
  }

  public function getTable($connection, $table) {
    $tables = $this->getTables($connection);
    return new drupalTableObj($connection, $table, $tables[$table]);
  }

  protected function tableDataCollector() {
    // Get all tables in the connected database.
    $dbal_tables = $this->connection->getSchemaManager()->listTables();

    // Get extra table information that DBAL does not provide.
    $db_tables_extra = $this->develTablesDriver->getExtraTableInfo($this->connection, $dbal_tables);

    // Build table information provided by Drupal schema system.
    $schema_tables = [];

    // Provided by modules, and list of prefixes used
    $modules = array_keys(system_get_info('module'));
    $prefixes = array();
    foreach($modules as $module) {
      $module_schema = drupal_get_module_schema($module);
      if (!empty($module_schema)) {
        foreach($module_schema as $table_name => $table_properties) {
          $schema_tables[$table_name]['module'] = 'module/' . $module;
          if (isset($table_properties['description'])) {
            $schema_tables[$table_name]['description'] = $table_properties['description'];
          } else {
            $schema_tables[$table_name]['description'] = t('*** No description available ***');
          }
          $table_prefix = Database::getConnection()->tablePrefix($table_name);
          $pfx = empty($table_prefix) ? '!null!' : $table_prefix;
          if (!isset($prefixes[$pfx])) {
            $prefixes[$pfx] = true;
          }
          $schema_tables[$table_name]['prefix'] = $table_prefix;
          $schema_tables[$table_name]['fields'] = $table_properties['fields'];
        }
      }
    }

    // Provided by entities.
    $table_prefix = Database::getConnection()->tablePrefix();
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    foreach($entities as $entity_name => $entity) {
      foreach([$entity->getBaseTable(), $entity->getDataTable(), $entity->getRevisionDataTable(), $entity->getRevisionTable()] as $table_name) {
        if ($table_name) {
          $schema_tables[$table_name]['module'] = 'entity/' . $entity_name;
          $schema_tables[$table_name]['prefix'] = $table_prefix;
        }
      }
    }

    $table_list = [];

    foreach ($dbal_tables as $dbal_table) {
      $table_name = $dbal_table->getName();
        if (strpos($table_name, $table_prefix) === 0) {
          $base_table_name = substr($table_name, strlen($table_prefix), strlen($table_name) - strlen($table_prefix));
          $table_list[$table_name] = array(
            'isDrupal' => true,
            'prefix' => $table_prefix,
            'base_name' => $base_table_name,
            'module' => empty($schema_tables[$table_name]['module']) ? '???' : $schema_tables[$table_name]['module'],
            'description' => $db_tables_extra[$table_name]['_description'],
            'rowsCount' => $db_tables_extra[$table_name]['_rows'],
            'DBAL' => $dbal_table,
            'extra' => $db_tables_extra[$table_name],
          );
        }
        else {
          $table_list[$table_name] = array(
            'isDrupal' => true,
            'prefix' => NULL,
            'base_name' => $table_name,
            'module' => empty($schema_tables[$table_name]['module']) ? '???' : $schema_tables[$table_name]['module'],
            'description' => $db_tables_extra[$table_name]['_description'],
            'rowsCount' => $db_tables_extra[$table_name]['_rows'],
            'DBAL' => $dbal_table,
            'extra' => $db_tables_extra[$table_name],
          );
        }
    }
    ksort($table_list);
    return $table_list;
  }

}
