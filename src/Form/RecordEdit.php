<?php

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
    // @todo a default setting if variable not defined
    $config = \Drupal::config('devel_tables.settings');
    $probe = \Drupal::service('devel_tables.probe')->connectDrupalDb($connection);
    $table_info = $probe->getTableInfo($table);
    $columns = $table_info['DBAL']->getColumns();
    $primary_key_string = base64_decode($record);
    //$primary_key = $probe->stringToPk($table_info, $record);
    $rec = $probe->getTableRow($table, $table_info, $primary_key_string);

    $form['#title'] = $table . ' - ' . $primary_key_string; // @todo too much on title

    $form['tableRec'] = [
      '#theme' => 'table',
      '#header' => [
        t('Field name'),
        t('Value'),
      ],
    ];

    $tabRow = 0;
    $rows = [];
    foreach ($columns as $a => $b) {
      $row = [];

      // $a has the column name
      // $b has the column object

      // Determines field type description.
      $type_name = $b->getType()->getName();
      switch ($type_name) {
        // Integer DBAL types.
        case 'smallint':
        case 'integer':
        case 'bigint':
        // Decimal DBAL types.
        case 'decimal':
        case 'float':
        // Bit DBAL types.
        case 'boolean':
        // Date and time DBAL types.
        case 'date':
        case 'datetime':
        case 'datetimetz':
        case 'time':
        case 'dateinterval':
          $field_type_description = $type_name;
          break;

        // String DBAL types.
        case 'string':
        case 'text':
        case 'guid':
        // Binary DBAL types.
        case 'binary':
        case 'blob':
        // Array DBAL types.
        case 'array':
        case 'simple_array':
        case 'json_array':
        // Object DBAL types.
        case 'object':
          if($b->getLength())
            $field_type_description = $type_name . '/' . $b->getLength();
          else
            $field_type_description = $type_name;
          break;

        default:
          $field_type_description = $type_name . '/' . $b->getLength();
      }
      if ($comment = $b->getComment()) { // @todo drupal comments'] || $b['nativeComment']) {
        $field_type_description .= " - " . $comment;
      }

      // Determines suffix for timestamp @todo make this abstract checking on int value
      $suffix_desc = null;
      if ($a == 'timestamp' || $a == 'created')    {
        $suffix_desc .= format_date((int) $rec[$a], 'devel_tables_date'); // @todo config
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
      if (FALSE) { //!$b['editable']) {
        $tmpx['#markup'] = $rec[$a];
      }
      else if ($type_name == 'blob') {
        if ($config->get('list_records.kint_blob')) {
          $tmp = @unserialize($rec[$a]);
          if (!$tmp) {
            $tmp = json_decode($rec[$a], TRUE);
          }
          if (!$tmp) {
            $tmp = jsonpp($rec[$a]);
          }
        }
        else {
          $tmp = strtr($rec[$a], array('{' => "\n\t", '}' => "\n"));
        }
        $tmpx['#markup'] = kdevel_print_object($tmp);
      }
      else {
        $tmpx['#default_value'] = $rec[$a];
        switch ($type_name) {
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
            if($b->getLength()) {
              $tmpx['#type'] = 'textfield';
              $size = ($b->getLength() > 60) ? 60 : $b->getLength();
              $tmpx['#size'] = $size;
              $tmpx['#maxlength'] = $b->getLength();
            }
            else    {
              $tmpx['#type'] = 'textarea';
              $tmpx['#cols'] = 100;
              $tmpx['#rows'] = 5;
            }
            break;
          default:
            $tmpx['#type'] = 'textfield';
            $size = ($b->getLength() > 60) ? 60 : $b->getLength();
            $tmpx['#size'] = $size;
            $tmpx['#maxlength'] = $b->getLength();
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
