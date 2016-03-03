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

  public function getTables($connection) {
    if ($cache = \Drupal::cache()->get("devel_tables:$connection:tableList")) {
      $tables = $cache->data;
    }
    else {
      $tables = $this->dbSchemaDataCollector($connection);
      \Drupal::cache()->set("devel_tables:$connection:tableList", $tables, Cache::PERMANENT); // @todo temporary
    }
    return $tables;
  }

  public function getTable($connection, $table) {
    $tables = $this->getTables($connection);
    return new drupalTableObj($connection, $table, $tables[$table]);
  }

  protected function dbSchemaDataCollector($connection) {
    $schema_tables = [];

    // Builds Drupal table descriptions from Drupal module schemae, and list of prefixes used
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

    // Builds Drupal table descriptions from Drupal entities.
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

    // Get all tables in the $connection database.
    $config = new Configuration();
    $options = Database::getConnection()->getConnectionOptions();
    $connectionParams = [
      'dbname' => $options['database'],
      'user' => $options['username'],
      'password' => $options['password'],
      'host' => $options['host'],
      'driver' => 'pdo_mysql',
    ];
    $conn = DriverManager::getConnection($connectionParams, $config);
    $dbal_tables = $conn->getSchemaManager()->listTables();

    // @todo generalise for other db
    $recs = $conn->query("show table status")->fetchAll();
    $db_tables_extra = [];
    foreach ($recs as $rec) {
      $db_tables_extra[$rec['Name']] = $rec;
    }
    $table_list = [];

    foreach ($dbal_tables as $dbal_table) {
      $table_name = $dbal_table->getName();
        if (strpos($table_name, $table_prefix) === 0) {
          $base_table_name = substr($table_name, strlen($table_prefix), strlen($table_name) - strlen($table_prefix));
          $table_list[$table_name] = array(
            'isDrupal' => true,
            'full_name' => $table_name,
            'prefix' => $table_prefix,
            'name' => $base_table_name,
            'module' => empty($schema_tables[$table_name]['module']) ? '???' : $schema_tables[$table_name]['module'],
            'description' => empty($db_tables_extra[$table_name]['Comment']) ? t('*** No description available ***'): $db_tables_extra[$table_name]['Comment'],
            'rowsCount' => $db_tables_extra[$table_name]['Rows'],
            'collation' => $db_tables_extra[$table_name]['Collation'],
            'storageMethod' => $db_tables_extra[$table_name]['Engine'],
          );
        }
        else {
          $table_list[$table_name] = array(
            'isDrupal' => true,
            'full_name' => $table_name,
            'prefix' => NULL,
            'name' => $table_name,
            'module' => empty($schema_tables[$table_name]['module']) ? '???' : $schema_tables[$table_name]['module'],
            'description' => empty($db_tables_extra[$table_name]['Comment']) ? t('*** No description available ***'): $db_tables_extra[$table_name]['Comment'],
            'rowsCount' => $db_tables_extra[$table_name]['Rows'],
            'collation' => $db_tables_extra[$table_name]['Collation'],
            'storageMethod' => $db_tables_extra[$table_name]['Engine'],
          );
        }
    }

    // Firstly process schema supported tables.
 /*   foreach ($db_tables as $table_name => $table_db_details) {
      foreach ($prefixes as $prefix => $d) {
        $table_prefix = ($prefix == '!null!') ? null : $prefix;
        if (!$table_prefix or strpos($table_name, $table_prefix) === 0) {
          if ($table_prefix) {
            $base_table_name = substr($table_name, strlen($table_prefix), strlen($table_name) - strlen($table_prefix));
          } else {
            $base_table_name = $table_name;
          }
          if (isset($schema_tables[$base_table_name])) {
            $row = array(
              'isDrupal' => true,
              'full_name' => $table_name,
              'prefix' => $schema_tables[$base_table_name]['prefix'],
              'name' => $base_table_name,
              'module' => isset($schema_tables[$base_table_name]['module']) ? $schema_tables[$base_table_name]['module'] : t('Drupal table ?'),
              'description' => isset($schema_tables[$base_table_name]['description']) ? $schema_tables[$base_table_name]['description'] : NULL,
              'rowsCount' => $table_db_details['rows'],
              'collation' => $table_db_details['collation'],
              'storageMethod' => $table_db_details['storageMethod'],
            );
            $table_list[$table_name] = $row;
            break;
          }
        }
      }
    }

    // Secondly process the rest db tables.
    foreach ($db_tables as $x => $table_db_details) {
      $table_name = $table_db_details->getName();
      if (!isset($table_list[$table_name])) {
        if (strpos($table_name, $table_prefix) === 0) {
          $base_table_name = substr($table_name, strlen($table_prefix), strlen($table_name) - strlen($table_prefix));
          $row = array(
            'isDrupal' => true,
            'full_name' => $table_name,
            'prefix' => $table_prefix,
            'name' => $base_table_name,
            'module' => '???',
            'description' => '',//$table_db_details['description'] ?: t('*** No description available ***'),
            'rowsCount' => '',//$table_db_details['rows'],
            'collation' => '',//$table_db_details['collation'],
            'storageMethod' => '',//$table_db_details['storageMethod'],
          );
        }
        else {
          $row = array(
            'isDrupal' => false,
            'full_name' => $table_name,
            'prefix' => null,
            'name' => $table_name,
            'module' => NULL,
            'description' => '',//$table_db_details['description'] ?: t('*** No description available ***'),
            'rowsCount' => '',//$table_db_details['rows'],
            'collation' => '',//$table_db_details['collation'],
            'storageMethod' => '',//$table_db_details['storageMethod'],
          );
        }
        $table_list[$table_name] = $row;
      }
      else {
        if (empty($table_list[$table_name]['description']) && $table_db_details['description']) {
          $table_list[$table_name]['description'] = $table_db_details['description'];
        }
      }
    }
*/
    ksort($table_list);
    return $table_list;
  }

}
