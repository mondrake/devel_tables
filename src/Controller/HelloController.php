<?php
/**
 * @file
 * Contains \Drupal\devel_tables\Controller\HelloController.
 */

namespace Drupal\devel_tables\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class HelloController extends ControllerBase {

  public function listTables() {
    $config = \Drupal::config('devel_tables.settings');
    $connection = 'default'; // @todo session based connection
    
    $tables = \Drupal::service('devel_tables.probe')->connectDrupalDb($connection)->getTables();

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
    foreach ($tables as $table_name => $table_properties) {
      $r = array();
      if ($config->get('list_tables.display_prefix')) {
        $r[] = $table_properties['prefix'];
      }
      $r[] = Link::fromTextAndUrl($table_name, new Url('devel_tables.table_records', [
        'connection' => $connection,
        'table' => $table_name,
      ]));
      $r[] = $table_properties['provider'];
      $r[] = $table_properties['description'];
      if ($config->get('list_tables.display_row_count')) {
        $r[] = $table_properties['rows_count'];
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

  public function tableRecords($connection, $table) {

  //    $menuParent = menu_get_active_trail();
  //    kpr($menuParent);

      // @todo a default setting if variable not defined
      $config = \Drupal::config('devel_tables.settings');

      $obj = \Drupal::service('devel_tables.probe')->getTable($connection, $table);
      $colDets = $obj->getColumnProperties();
      $total = $obj->count();
      $limit = 50;
      $pageNo = pager_find_page(4); 
      pager_default_initialize($total, $limit, 4);
      $objs = $obj->listAll(NULL, $limit , $pageNo * $limit);

      $header = array();
      $header[] =  array('data' => t('#'));
      foreach ($colDets as $c => $d)    {
        $header[] = $c;
/*          $header[] = theme('textimage_style_image', array(
              'style_name' => 'verthead', 
              'text'   => $c,
              'alt'   => $c,
              'title'   => $c,
          ));*/
      }

      $rows = array();
      $j = ($pageNo * $limit) + 1;
      if ($objs) {
          foreach ($objs as $a => $b) {        // $b is the record
              $row = array();
              $enc = base64_encode($b->primaryKeyString);
              $row[] = $j;
              foreach ($colDets as $c => $d)    {        // $c is the field, $d is the value
                  if ($d['type'] == 'blob')    {  // @todo check a value exists
                      switch ($config->get('list_records.display_lob')) {
                      case 'label':
                          $tmp = '*BLOB* ' . strlen($b->$c);
                          break;
                      case 'text':
                      default:
                          $tmp = _DTTextTrim($d, $b->$c);
                          break;
                      }
                  } elseif ($c == 'timestamp' || $c == 'created'|| $c == 'expire') {
                      $tmp = format_date((int) $b->$c, 'full');
                  } else {
                      $tmp = _DTTextTrim($d, $b->$c);
                  }
                  if ($d['primaryKey']) {
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
      
      //$build = t('Rows #: ') . $total;
      
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
