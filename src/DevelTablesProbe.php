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

  public function connectDrupalDb($connection_key = 'default') {
    $this->connectionType = 'drupal';
    $this->connectionKey = $connection_key;
    $config = new Configuration();
    $options = Database::getConnectionInfo($connection_key);

    // @todo make it dependent on driver + deal with master/slave setups
    $connection_parms = [
      'dbname' => $options['default']['database'],
      'user' => $options['default']['username'],
      'password' => $options['default']['password'],
      'host' => $options['default']['host'],
      'driver' => 'pdo_mysql',
      'charset' => 'utf8',
    ];
    $this->connection = DriverManager::getConnection($connection_parms, $config);
    return $this;
  }

  public function getTables() {
    if ($cache = \Drupal::cache()->get("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList")) {
      $tables = $cache->data;
    }
    else {
      $tables = $this->dbSchemaDataCollector();
      \Drupal::cache()->set("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList", $tables, Cache::PERMANENT); // @todo temporary
    }
    return $tables;
  }

  public function getTable($connection, $table) {
    $tables = $this->getTables($connection);
    return new drupalTableObj($connection, $table, $tables[$table]);
  }

  protected function dbSchemaDataCollector() {
    // Get all tables in the connected database.
    $dbal_tables = $this->connection->getSchemaManager()->listTables();

    // Get extra table information that DBAL does not provide.
    // @todo generalise for other db
    $recs = $this->connection->query("show table status")->fetchAll();
    $db_tables_extra = [];
    foreach ($recs as $rec) {
      $db_tables_extra[$rec['Name']] = $rec;
    }

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
            'description' => empty($db_tables_extra[$table_name]['Comment']) ? t('*** No description available ***'): $db_tables_extra[$table_name]['Comment'],
            'rowsCount' => $db_tables_extra[$table_name]['Rows'],
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
            'description' => empty($db_tables_extra[$table_name]['Comment']) ? t('*** No description available ***'): $db_tables_extra[$table_name]['Comment'],
            'rowsCount' => $db_tables_extra[$table_name]['Rows'],
            'DBAL' => $dbal_table,
            'extra' => $db_tables_extra[$table_name],
          );
        }
    }
    ksort($table_list);
    return $table_list;
  }

}
