<?php
/**
 * @file
 * Contains \Drupal\devel_tables\Controller\TableListController.
 */

namespace Drupal\devel_tables\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * @todo
 */
class TableListController extends ControllerBase {

  /**
   * Builds the state variable overview page.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function listTables() {

    $header = array(
      'name' => array('data' => t('Name')),
      'value' => array('data' => t('Value')),
      'edit' => array('data' => t('Operations')),
    );

    $rows = array();
    // State class doesn't have getAll method so we get all states from the
    // KeyValueStorage and put them in the table.
    foreach ($this->keyValue('state')->getAll() as $state_name => $state) {
      $operations['edit'] = array(
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('devel.system_state_edit', array('state_name' => $state_name)),
      );
      $rows[$state_name] = array(
        'name' => $state_name,
        'value' => kprint_r($state, TRUE),
        'edit' => array(
          'data' => array(
            '#type' => 'operations',
            '#links' => $operations,
          )
        ),
      );
    }

    $output['states'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No state variables.'),
    );

    return $output;
  }

}
