<?php
/**
 * @file
 * Contains \Drupal\devel_tables\Controller\HelloController.
 */

namespace Drupal\devel_tables\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

class HelloController extends ControllerBase {
  public function content() {
    // @todo a default setting if variable not defined
    $config = \Drupal::config('devel_tables.settings')->get();
  
    $DTTables = $this->DTGetTables($connection);
 
    // filter by module
   // $build['DTtables_filter_form'] = drupal_get_form('DTtables_filter_form');
//kpr($build);
    

    // prepares table headers
    $header = array();
    if ($config['list_tables']['display_prefix']) {
        $header[] =  array('data' => t('Prefix'));
    }
    $header[] =  array('data' => t('Table'), 'sort' => 'asc');
    $header[] =  array('data' => t('Module'));
    $header[] =  array('data' => t('Description'));
    if ($config['list_tables']['display_row_count']) {
        $header[] =  array('data' => t('# rows'));
    }
    if ($config['list_tables']['display_collation']) {
        $header[] =  array('data' => t('Collation'));
    }
    if ($config['list_tables']['display_storage_method']) {
        $header[] =  array('data' => t('Storage'));
    }

    // prepares table rows
    $rows = array();
/*    foreach ($DTTables as $DTName => $DTProperties) {
        $r = array();
        if ($config['list_tables']['display_prefix']) {
            $r[] = $DTProperties['prefix']; 
        }
        $r[] = l($DTProperties['name'], "devel_tables/tables/$connection/table/$DTName");
        $r[] = $DTProperties['module'];
        $r[] = $DTProperties['description'];
        if ($config['list_tables']['display_row_count']) {
            if (module_exists('format_number')) {
                $r[] = '<div align=right>' . format_number($DTProperties['rowsCount'] . '</div>');
            } else {
                $r[] = '<div align=right>' . $DTProperties['rowsCount'] . '</div>';
            }
        }
        if ($config['list_tables']['display_collation']) {
            $r[] = $DTProperties['collation'];
        }
        if ($config['list_tables']['display_storage_method']) {
            $r[] = $DTProperties['storageMethod'];
        }
        $rows[] = $r;
    }
  */
    // render table
/*    $output .= theme_table(
        array(
            'header' => $header,
            'rows' => $rows,
            'attributes' => array(),
            'caption' => null,
            'colgroups' => null,
            'sticky' => true,
            'empty' => t('No data has been collected.'),
        )
    );*/
    $build['DTtables'] = array(
        array(
            '#theme' => 'table',   
            '#header' => $header,
            '#rows' => $rows,
            '#attributes' => array(),
            '#caption' => null,
            '#colgroups' => null,
            '#sticky' => true,
            '#empty' => t('No data has been collected.'),
        )
    );
    
    //menu_set_active_trail();

    return $build;
/*    return array(
        '#type' => 'markup',
        '#markup' => $this->t('Hello, World!'),
    );*/
  }

  protected function DTGetTables($connection) {
/*      $cg = cache_get("devel_tables:$connection:tableList");
      if ($cg) {
          $DTTables = $cg->data;
      } else {*/
          $DTTables = $this->DTTablesDataCollector($connection);
/*          cache_set("devel_tables:$connection:tableList", $DTTables, 'cache' , CACHE_TEMPORARY);
      }*/
      return $DTTables;
  }

  protected function DTTablesDataCollector($connection) {
      // builds Drupal table descriptions from Drupal schemas, and list of prefixes used
      //$modules = db_query("SELECT * FROM {system} WHERE type = 'module' ORDER BY weight ASC, name ASC");
      $modules = array_keys(system_get_info('module'));
      $drupalTableDescriptions = array();
      $prefixes = array();
      foreach($modules as $module)    {
          $schemaUnp = drupal_get_module_schema($module);
          if (!empty($schemaUnp)) {
              foreach($schemaUnp as $drupalTableName => $drupalTableProperties)    {
                  $drupalTableDescriptions[$drupalTableName]['module'] = $module;
                  if (isset($drupalTableProperties['description'])) {
                      $drupalTableDescriptions[$drupalTableName]['description'] = $drupalTableProperties['description'];
                  } else {
                      $drupalTableDescriptions[$drupalTableName]['description'] = t('*** No description available ***');
                  }
                  $drupalTablePrefix = Database::getConnection()->tablePrefix($drupalTableName);
                  $pfx = empty($drupalTablePrefix) ? '!null!' : $drupalTablePrefix;
                  if (!isset($prefixes[$pfx])) {
                      $prefixes[$pfx] = true;
                  }
                  $drupalTableDescriptions[$drupalTableName]['prefix'] = $drupalTablePrefix;
                  //$drupalTableDescriptions[$drupalTableName]['fields'] = $drupalTableProperties['fields'];
              }
          }
      }

      // get all tables in the $connection database
      $dto = new drupalTableObj($connection);
      $tables = $dto->fetchAllTables();

      $tableList = array();
      foreach ($tables as $table => $b) {
          foreach ($prefixes as $prefix => $d) {
              $tablePrefix = ($prefix == '!null!') ? null : $prefix;
              if (!$tablePrefix or strpos($table, $tablePrefix) === 0) {
                  if ($tablePrefix) {
                      $unPrefixedTN = substr($table, strlen($tablePrefix), strlen($table) - strlen($tablePrefix)); 
                  } else {
                      $unPrefixedTN = $table; 
                  }
                  if (isset($drupalTableDescriptions[$unPrefixedTN])) {
                      $row = array(
                          'isDrupal' => true,
                          'prefix' => $drupalTableDescriptions[$unPrefixedTN]['prefix'],
                          'name' => $unPrefixedTN,
                          'module' => isset($drupalTableDescriptions[$unPrefixedTN]['module']) ? $drupalTableDescriptions[$unPrefixedTN]['module'] : t('Drupal table ?'),
                          'description' => isset($drupalTableDescriptions[$unPrefixedTN]['description']) ? $drupalTableDescriptions[$unPrefixedTN]['description'] : t('*** No description available ***'),
                          'rowsCount' => $b['rows'],
                          'collation' => $b['collation'],
                          'storageMethod' => $b['storageMethod'],
                      );
                      $tableList[$table] = $row;
                      break;
                  } 
              }
          }
          if (!isset($tableList[$table])) {
              $row = array(
                  'isDrupal' => false,
                  'prefix' => null,
                  'name' => $table,
                  'module' => t('Drupal table ?'),
                  'description' => t('*** No description available ***'),
                  'rowsCount' => $b['rows'],
                  'collation' => $b['collation'],
                  'storageMethod' => $b['storageMethod'],
              );
              $tableList[$table] = $row;
          }
      }
kint($tableList);
      return $tableList;
  }

}
