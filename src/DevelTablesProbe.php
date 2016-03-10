<?php

namespace Drupal\devel_tables;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Database;
use Drupal\devel_tables\Plugin\DevelTablesDriverPluginManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

/**
 * @todo
 */
class DevelTablesProbe {

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The devel_tables driver plugin manager.
   *
   * @var \Drupal\devel_tables\Plugin\DevelTablesDriverPluginManager
   */
  protected $develTablesDriverManager;

  protected $connectionType;
  protected $connectionKey;
  protected $connection;
  protected $develTablesDriver;

  /**
   * Constructs a DevelTablesProbe object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Drupal\devel_tables\Plugin\DevelTablesDriverPluginManager $driver_plugin_manager
   *   The cache service.
   */
  public function __construct(CacheBackendInterface $cache_service, DevelTablesDriverPluginManager $driver_plugin_manager) {
    $this->cache = $cache_service;
    $this->develTablesDriverManager = $driver_plugin_manager;
  }

  public function connectDrupalDb($connection_key = 'default') {
    $this->connectionType = 'drupal';
    $this->connectionKey = $connection_key;

    // Get Drupal connection info.
    $drupal_connection_info = Database::getConnectionInfo($connection_key)['default']; // @todo allow selecting replicas?

    // Get DBAL connection info from the mapper plugin.
    $this->develTablesDriver = $this->develTablesDriverManager->createInstance($drupal_connection_info['driver']);
    $connection_parms = $this->develTablesDriver->getConnectionInfo($drupal_connection_info);

    // Connect to the database via DBAL.
    $this->connection = DriverManager::getConnection($connection_parms, new Configuration());
    return $this;
  }

  public function getTables() {
    if ($cache = $this->cache->get("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList")) {
      $tables = $cache->data;
    }
    else {
      $tables = $this->tableDataCollector();
      $this->cache->set("devel_tables:{$this->connectionType}:{$this->connectionKey}:tableList", $tables, Cache::PERMANENT); // @todo temporary
    }
    return $tables;
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
          $schema_tables[$table_name]['provider'] = 'module/' . $module;
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
          $schema_tables[$table_name]['provider'] = 'entity/' . $entity_name;
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
            'prefix' => $table_prefix,
            'base_name' => $base_table_name,
            'description' => $db_tables_extra[$table_name]['_description'],
            'rows_count' => $db_tables_extra[$table_name]['_rows'],
            'DBAL' => $dbal_table,
            'extra' => $db_tables_extra[$table_name],
          );
          if (isset($schema_tables[$base_table_name])) {
            $table_list[$table_name]['drupal'] = $schema_tables[$base_table_name];
          }
        }
        else {
          $table_list[$table_name] = array(
            'prefix' => NULL,
            'base_name' => $table_name,
            'description' => $db_tables_extra[$table_name]['_description'],
            'rows_count' => $db_tables_extra[$table_name]['_rows'],
            'DBAL' => $dbal_table,
            'extra' => $db_tables_extra[$table_name],
          );
          if (isset($schema_tables[$base_table_name])) {
            $table_list[$table_name]['drupal'] = $schema_tables[$base_table_name];
          }
        }
    }
    ksort($table_list);
    return $table_list;
  }

  public function getTable($table) {
    $tables = $this->getTables();
    return $tables[$table];
  }

  public function getTableRowsCount($table) {
    $res = $this->connection->createQueryBuilder()
      ->select('count(*) as rows_count')
      ->from($table)
      ->execute()
      ->fetch();
    return $res['row_count'];
  }

  public function getTableRows($table, $whereClause = NULL, $limit = NULL, $offset = NULL) {
    $query_builder = $this->connection->createQueryBuilder()
      ->select('*')
      ->from($table);
    if ($whereClause) {
      $query_builder->where($whereClause);
    }
    if ($offset) {
      $query_builder->setFirstResult($offset);
    }
    if ($limit) {
      $query_builder->setMaxResults($limit);
    }
    $res = $query_builder
      ->execute()
      ->fetchAll();
    return $res;
  }

}
