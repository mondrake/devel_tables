<?php

/**
 * @file
 * Contains \Drupal\devel_tables\Form\RecordEdit.
 */

namespace Drupal\devel_tables\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main devel_tables settings admin form.
 */
class RecordEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'devel_tables_record_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $connection = NULL, $table = NULL, $record = NULL) {

    $config = \Drupal::config('devel_tables.settings');

    $obj = \Drupal::service('devel_tables.probe')->getTable($connection, $table);
    $colDets = $obj->getColumnProperties();
    $obj->read(base64_decode($record));

    $form['#title'] = $table . ' - ' . $obj->primaryKeyString; // @todo too much on title

    $form['tableRec'] = [
      '#theme' => 'table',
      '#header' => [
        t('Field name'),
        t('Value'),
      ],
    ];

    $tabRow = 0;
    $rows = [];
    foreach ($colDets as $a => $b) {
      $row = [];

      // $a has the field name
      // $b has the field properties

      // Determines field type description.
      switch ($b['type']) {
        case 'boolean':
        case 'integer':
        case 'time':
        case 'date':
        case 'timestamp':
          $field_type_description = $b['type'];
          break;
        case 'text':
        case 'blob':
          if($b['length'])
            $field_type_description = $b['type'] . '/' . $b['length'];
          else
            $field_type_description = $b['type'];
          break;
        default:
          $field_type_description = $b['type'] . '/' . $b['length'];
      }
      if ($b['comment'] || $b['nativeComment']) {
        $field_type_description .= " - " . (!empty($b['comment']) ? $b['comment'] : $b['nativeComment']);
      }

      // Determines suffix for timestamp @todo make this abstract checking on int value
      $suffix_desc = null;
      if ($a == 'timestamp' || $a == 'created')    {
        $suffix_desc .= format_date((int) $obj->$a, 'full');
      }

      // output field name
/*      $row[] = [
        'data' => $a,
      ];
*/
      // determines how to output field
      $tmpx = [
        '#title' => $a,
        '#type' => 'item',
        '#description' => $field_type_description,
        '#field_suffix' => $suffix_desc,
      ];
      if (!$b['editable']) {
        $tmpx['#markup'] = $obj->$a;
      }
      else if ($b['type'] == 'blob') {
        if ($config->get('list_records.kint_blob')) {
          $tmp = @unserialize($obj->$a);
          if (!$tmp) {
            $tmp = json_decode($obj->$a, TRUE);
          }
          if (!$tmp) {
            $tmp = jsonpp($obj->$a);
          }
        }
        else {
          $tmp = strtr($obj->$a, array('{' => "\n\t", '}' => "\n"));
        }
        $tmpx['#markup'] = kdevel_print_object($tmp);
      }
      else {
        $tmpx['#default_value'] = $obj->$a;
        switch ($b['type']) {
          case 'boolean':
            $tmpx['#type'] = 'checkbox';
            break;
          case 'integer':
          case 'time':
            $tmpx['#type'] = 'number';
            $tmpx['#size'] = 12;
            break;
          case 'date':
            $tmpx['#type'] = 'textfield';
            $tmpx['#size'] = 12;
            $tmpx['#attributes'] = array ( 'class' => array('datepicker') );
          case 'timestamp':
            $tmpx['#type'] = 'number';
            $tmpx['#size'] = 22;
            break;
          case 'text':
          case 'blob':
            if($b['length']) {
              $tmpx['#type'] = 'textfield';
              $size = ($b['length'] > 60) ? 60 : $b['length'];
              $tmpx['#size'] = $size;
              $tmpx['#maxlength'] = $b['length'];
            }
            else    {
              $tmpx['#type'] = 'textarea';
              $tmpx['#cols'] = 100;
              $tmpx['#rows'] = 5;
            }
            break;
          default:
            $tmpx['#type'] = 'textfield';
            $size = ($b['length'] > 60) ? 60 : $b['length'];
            $tmpx['#size'] = $size;
            $tmpx['#maxlength'] = $b['length'];
        }
/*        $row[] = [
          'data' => $tmpx,
        ];*/
      }
//      $rows[] = $row;
      $tabRow++;
      $form[$a] = $tmpx; // @todo
    }
    $form['tableRec']['#rows'] = $rows;

    //krumo($form['tableRec']);
    //$form['submit'] = array(
    //    '#type' => 'submit',
    //    '#value' => t('Update'));

    //$form['#after_build'] = array('_mondrake_objedit_form_after_build');

    // enhance breadcrumbs
    //$breadcrumbs = drupal_get_breadcrumb();
    //$breadcrumbs[] = l(t('Table Browser'),'admin/drupalbrowser');
    //$breadcrumbs[] = l(t($table),'admin/drupallist/' . $table );
    //drupal_set_breadcrumb($breadcrumbs);
//kint($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
