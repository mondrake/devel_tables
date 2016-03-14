<?php

namespace Drupal\devel_tables\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class TableController extends ControllerBase {

  public function refresh() {
    return ['#markup' => 'xxxx'];
  }

  public function listTablesTitle() {
    return $this->t("Database: %database", ['%database' => 'drupal/default']);
  }

  public function listTables() {
    $config = \Drupal::config('devel_tables.settings');
    $connection = 'default'; // @todo session based connection
    $probe = \Drupal::service('devel_tables.probe')->connectDrupalDb($connection);
    $tables = $probe->getTableList();

    // prepares table headers
    $header = array();
    if ($config->get('list_tables.display_prefix')) {
      $header[] =  array('data' => t('Prefix'));
    }
    $header[] =  array('data' => t('Table'), 'sort' => 'asc');
    $header[] =  array('data' => t('Provided by'));
    $header[] =  array('data' => t('Description'));
    if ($config->get('list_tables.display_row_count')) {
      $header[] =  array('data' => t('# rows'));
    }

    // prepares table rows
    $rows = array();
    foreach ($tables as $table_name) {
      $r = [];
      $table_info = $probe->getTableInfo($table_name);
      if ($config->get('list_tables.display_prefix')) {
        $r[] = $table_info['prefix'];
      }
      $r[] = Link::fromTextAndUrl($table_info['base_name'], new Url('devel_tables.table_records', [
        'connection' => $connection,
        'table' => $table_name,
      ]));
      $r[] = isset($table_info['drupal']['provider']) ? $table_info['drupal']['provider'] : NULL;
      $r[] = $table_info['description'];
      if ($config->get('list_tables.display_row_count')) {
        $r[] = $table_info['rows_count'];
      }
      $rows[] = $r;
    }

    // render table
    $build[] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => array(),
      '#caption' => null,
      '#colgroups' => null,
      '#sticky' => true,
      '#empty' => t('No data has been collected.'),
    ];
    return $build;
  }

  public function tableRecordsTitle($connection, $table) {
    return $this->t("Table: %table", ['%table' => $table]);
  }

  public function tableRecords($connection, $table) {
      // @todo a default setting if variable not defined
      $config = \Drupal::config('devel_tables.settings');
      $probe = \Drupal::service('devel_tables.probe')->connectDrupalDb($connection);
      $table_info = $probe->getTableInfo($table);
      $columns = $table_info['DBAL']->getColumns();
      $rows_count = $probe->getTableRowsCount($table);
      $limit = 50;
      $current_page = pager_find_page(4);
      pager_default_initialize($rows_count, $limit, 4);
      $records = $probe->getTableRows($table, NULL, $limit , $current_page * $limit);

      $header = array();
      $header[] =  array('data' => t('#'));
      foreach ($columns as $c => $d)    {
        $header[] = $c;
/*          $header[] = theme('textimage_style_image', array(
              'style_name' => 'verthead',
              'text'   => $c,
              'alt'   => $c,
              'title'   => $c,
          ));*/
      }

      $rows = array();
      $j = ($current_page * $limit) + 1;
      if ($records) {
          foreach ($records as $a => $b) {        // $b is the actual record
              $row = array();
              $enc = base64_encode($probe->pkToString($table_info,$b));
              $row[] = $j;
              foreach ($columns as $c => $d)    {        // $c is the column name, $d is the column definition
                if ($d->getType()->getName() == 'blob')    {  // @todo check a value exists
                      switch ($config->get('list_records.display_lob')) {
                      case 'label':
                          $tmp = '*BLOB* ' . strlen($b[$c]);
                          break;
                      case 'text':
                      default:
                          $tmp = _DTTextTrim(isset($table_info['primary_key'][$c]), $b[$c]);
                          break;
                      }
                  } elseif ($c == 'timestamp' || $c == 'created'|| $c == 'expire') {
                      $tmp = format_date((int) $b[$c], 'devel_tables_date'); // @todo config
                  } else {
                      $tmp = _DTTextTrim(isset($table_info['primary_key'][$c]), $b[$c]);
                  }
                  if (isset($table_info['primary_key'][$c])) {
                      $row[] = Link::fromTextAndUrl($tmp, new Url('devel_tables.record_edit', [
                        'connection' => $connection,
                        'table' => $table,
                        'record' => $enc,
                      ]));
                  } else {
                      $row[] = $tmp;
                  }
              }
              $rows[] = $row;
              $j++;
          }
      }

      //drupal_set_title($DTTables[$table]['name']);

      //$build = t('Rows #: ') . $rows_count;

      $build[] = [
        '#type' => 'pager',
        '#element' => 4,
        /*'#quantity' => 9,
        '#route_name' => '<current>',*/
      ];

      $build[] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array(),
        '#caption' => NULL,
        '#colgroups' => NULL,
        '#sticky' => TRUE,
        '#empty' => t('No data has been collected.'),
      ];

      // enhance breadcrumbs
      //$breadcrumbs = drupal_get_breadcrumb();
      //$breadcrumbs[] = l(t('Tables'),'devel_tables/tables');
      //drupal_set_breadcrumb($breadcrumbs);

      return $build;
  }

}
