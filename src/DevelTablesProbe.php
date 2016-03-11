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
   * Constants @todo.
   */
  const PK_SEPARATOR = '|';
  const PK_SEPARATOR_REPLACE = '#&!vbar!&#';

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
      $tables = array_keys($this->tableDataCollector());
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

    // Provided by SQL storage entities.
    $table_prefix = Database::getConnection()->tablePrefix();
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    foreach($entity_types as $entity_type_name => $entity_type) {
      $entity_type_storage = \Drupal::entityManager()->getStorage($entity_type_name);
//if($entity_type_name == 'block_content') { kint($entity_type_storage->getFieldStorageDefinitions()); }
      if ($entity_type_storage instanceof \Drupal\Core\Entity\Sql\SqlEntityStorageInterface) {
        $mapping = $entity_type_storage->getTableMapping();
        // Entity level tables.
        foreach($mapping->getTableNames() as $table_name) {
          $schema_tables[$table_name]['provider'] = 'entity type/' . $entity_type_name;
          $schema_tables[$table_name]['prefix'] = $table_prefix;
        }
      }
    }



/*if ($entity_type instanceof \Drupal\Core\Entity\ContentEntityType) {
  kint($entity_type_name);
kint(\Drupal::entityManager()->getStorage($entity_type_name));
  $foo = \Drupal::entityManager()->getBundleInfo($entity_type_name);
  $fox = [];
  foreach($foo as $bundle_name => $bundle) {
    $field_defs = \Drupal::entityManager()->getFieldDefinitions($entity_type_name, $bundle_name);
    $fox[] = [$bundle_name, implode(', ', array_keys($field_defs))];
    //kint(\Drupal::entityManager()->getFieldStorageDefinitions($entity_type_name));
/*    foreach($field_defs as $field_name => $field) {
      if ($field_config = \Drupal\field\Entity\FieldConfig::loadByName($entity_type_name, $bundle_name, $field_name)) {
        kint($field_config->getFieldStorageDefinition());
        kint($field_config->getFieldStorageDefinition()->getSchema());
      }
    }
  }
  //kint($fox);
}*/



/*$foo = \Drupal::entityManager()->getFieldMap();
kint($foo);
$foo = \Drupal::entityManager()->getAllBundleInfo();
kint($foo);
//$foo = \Drupal::entityManager()->getBundleInfo($entity_type);
$foo = \Drupal::entityManager()->getBundleInfo('node');
kint($foo);
//$foo = \Drupal\field\Entity\FieldConfig::loadByName($entity_type, $bundle, $field_name);
$foo = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', 'body');
kint($foo);
//$foo = \Drupal::entityManager()->getFieldDefinitions($entity_type, $bundle);
$foo = \Drupal::entityManager()->getFieldDefinitions('node', 'article');
kint($foo);
//$foo = \Drupal::entityManager()->getFieldStorageDefinitions($entity_type);
$foo = \Drupal::entityManager()->getFieldStorageDefinitions('node');
kint($foo);*/
/*$foo = \Drupal::entityManager()->getStorage('field_storage_config')->loadMultiple();
foreach ($foo as $name => $conf) {
  kint($name);
  //kint($conf);
  //kint([$conf->getTargetEntityTypeId(), $conf->getBundles()]);
  $field_info = \Drupal::entityManager()->getStorage($conf->getTargetEntityTypeId());
  //kint($field_info->getTableMapping());
  kint($field_info->getTableMapping()->getTableNames());
  kint($field_info->getTableMapping()->getDedicatedTableNames());
}*/

    // Provided by fields.
/*    $fields = \Drupal::entityManager()->getStorage('field_storage_config')->loadMultiple();
    foreach($fields as $field_name => $field) {
      $mapping = \Drupal::entityManager()->getStorage($field->getTargetEntityTypeId())->getTableMapping();
//      kint($mapping->getDedicatedTableNames());
      foreach($mapping->getTableNames() as $table_name) {
        if ($table_name) {
          $schema_tables[$table_name]['provider'] = 'entity/' . $field->getTargetEntityTypeId();
          $schema_tables[$table_name]['prefix'] = $table_prefix;
        }
      }
      foreach($mapping->getDedicatedTableNames() as $table_name) {
        if ($table_name) {
          $schema_tables[$table_name]['provider'] = 'field/' . $field_name;
          $schema_tables[$table_name]['prefix'] = $table_prefix;
        }
      }
    }
*/



    $table_list = [];

    foreach ($dbal_tables as $dbal_table) {
      $table_name = $dbal_table->getName();
      if (strpos($table_name, $table_prefix) === 0) {
        $base_table_name = substr($table_name, strlen($table_prefix), strlen($table_name) - strlen($table_prefix));
        $table_list[$table_name] = array(
          'prefix' => $table_prefix,
          'base_name' => $base_table_name,
          'primary_key' => array_combine($dbal_table->getPrimaryKey()->getColumns(), $dbal_table->getPrimaryKey()->getColumns()),
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
          'primary_key' => array_combine($dbal_table->getPrimaryKey()->getColumns(), $dbal_table->getPrimaryKey()->getColumns()),
          'description' => $db_tables_extra[$table_name]['_description'],
          'rows_count' => $db_tables_extra[$table_name]['_rows'],
          'DBAL' => $dbal_table,
          'extra' => $db_tables_extra[$table_name],
        );
        if (isset($schema_tables[$base_table_name])) {
          $table_list[$table_name]['drupal'] = $schema_tables[$base_table_name];
        }
      }
      $this->cache->set("devel_tables:{$this->connectionType}:{$this->connectionKey}:table:{$table_name}", $table_list[$table_name], Cache::PERMANENT); // @todo temporary
    }
    ksort($table_list);
    return $table_list;
  }

  public function getTable($table_name) {
    return $this->cache->get("devel_tables:{$this->connectionType}:{$this->connectionKey}:table:{$table_name}")->data;
  }

  public function getTableRowsCount($table) {
    $res = $this->connection->createQueryBuilder()
      ->select('count(*) as rows_count')
      ->from($table)
      ->execute()
      ->fetch();
    return isset($res['rows_count']) ? $res['rows_count'] : 0;
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

  public function getTableRow($table, $table_info, $primary_key_string) {
    $primary_key = $this->pkStringToArray($table_info, $primary_key_string);
    $query_builder = $this->connection->createQueryBuilder()
      ->select('*')
      ->from($table);
    $j = 0;
    foreach ($primary_key as $column => $value) {
      if (isset($prepared_where)) {
        $prepared_where .= ' and ' . $column . ' = ? ';
      } else {
        $prepared_where = $column . ' = ? ';
      }
      $query_builder->setParameter($j, $value);
      $j++;
    }
    $query_builder->where($prepared_where);
    $res = $query_builder
      ->execute()
      ->fetch();
    return $res;
  }

  public function pkToString($table_info, $record) {
    foreach ($table_info['primary_key'] as $c) {
      if (isset($record[$c])) {
        $tok = str_replace(static::PK_SEPARATOR, static::PK_SEPARATOR_REPLACE, $record[$c]); // @todo unicode replace
      }
      else {
        $tok = NULL;
      }
      if (isset($res)) {
        $res .= static::PK_SEPARATOR . $tok;
      }
      else {
        $res = $tok;
      }
    }
    return $res;
  }

  public function pkStringToArray($table_info, $primary_key_string) {
    $attrs = explode(static::PK_SEPARATOR, $primary_key_string);
    $j = 0;
    $res = [];
    foreach ($table_info['primary_key'] as $c) {
      $e = str_replace(static::PK_SEPARATOR_REPLACE, static::PK_SEPARATOR, $attrs[$j]); // @todo unicode
      $res[$c] = $e;
      $j++;
    }
    return $res;
  }

}
