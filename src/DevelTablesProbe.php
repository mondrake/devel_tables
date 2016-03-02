<?php

/**
 * @file
 * Contains \Drupal\devel_tables\DevelTablesProbe.
 */

namespace Drupal\devel_tables;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\devel_tables\drupalTableObj;

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

    // get all tables in the $connection database
    $dto = new drupalTableObj($connection);
    $db_tables = $dto->fetchAllTables();

    $table_list = [];

    // Firstly process schema supported tables.
    foreach ($db_tables as $table_name => $table_db_details) {
      foreach ($prefixes as $prefix => $d) {
        $tablePrefix = ($prefix == '!null!') ? null : $prefix;
        if (!$tablePrefix or strpos($table_name, $tablePrefix) === 0) {
          if ($tablePrefix) {
            $unPrefixedTN = substr($table_name, strlen($tablePrefix), strlen($table_name) - strlen($tablePrefix));
          } else {
            $unPrefixedTN = $table_name;
          }
          if (isset($schema_tables[$unPrefixedTN])) {
            $row = array(
              'isDrupal' => true,
              'full_name' => $table_name,
              'prefix' => $schema_tables[$unPrefixedTN]['prefix'],
              'name' => $unPrefixedTN,
              'module' => isset($schema_tables[$unPrefixedTN]['module']) ? $schema_tables[$unPrefixedTN]['module'] : t('Drupal table ?'),
              'description' => isset($schema_tables[$unPrefixedTN]['description']) ? $schema_tables[$unPrefixedTN]['description'] : NULL,
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

    // @todo find it in config
    $prefix = 'lab05_';

    // Secondly process the rest db tables.
    foreach ($db_tables as $table_name => $table_db_details) {
      if (!isset($table_list[$table_name])) {
        if (strpos($table_name, $tablePrefix) === 0) {
          $unPrefixedTN = substr($table_name, strlen($prefix), strlen($table_name) - strlen($prefix));
          $row = array(
            'isDrupal' => true,
            'full_name' => $table_name,
            'prefix' => $prefix,
            'name' => $unPrefixedTN,
            'module' => '???',
            'description' => $table_db_details['description'] ?: t('*** No description available ***'),
            'rowsCount' => $table_db_details['rows'],
            'collation' => $table_db_details['collation'],
            'storageMethod' => $table_db_details['storageMethod'],
          );
        }
        else {
          $row = array(
            'isDrupal' => false,
            'full_name' => $table_name,
            'prefix' => null,
            'name' => $table_name,
            'module' => NULL,
            'description' => $table_db_details['description'] ?: t('*** No description available ***'),
            'rowsCount' => $table_db_details['rows'],
            'collation' => $table_db_details['collation'],
            'storageMethod' => $table_db_details['storageMethod'],
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

    ksort($table_list);
    return $table_list;
  }

}
