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

    //drupal_set_title($DTTables[$table]['name'] . ' - ' . $obj->primaryKeyString);
    
   // $form['krumo_head'] = array(
   //     '#markup' => krumo::dump_css(),);

    $form['tableRec'] = [
      '#theme' => 'table',
      '#header' => [
        t('Field name'),
        t('Value'),
      ],
    ];
    
    $tabRow = 0;
    $rows = [];
    foreach ($colDets as $a => $b)    {
        $row = [];

        // $a has the field name
        // $b has the field properties
        
        // determines field description
        switch ($b['type'])    {
            case 'boolean':
            case 'integer':
            case 'time':
            case 'date':
            case 'timestamp':
                $fieldTypeDesc = $b['type'];
                break;
            case 'text':
            case 'blob':
                if($b['length']) 
                    $fieldTypeDesc = $b['type'] . '/' . $b['length'];
                else
                    $fieldTypeDesc = $b['type'];
                break;
            default:
                $fieldTypeDesc = $b['type'] . '/' . $b['length'];
        }
        if ($b['comment'])    {
            $fieldTypeDesc .= " - " . $b['comment'];
        }

        $suffixDesc = null;
        if ($a == 'timestamp' || $a == 'created')    {
            $suffixDesc .= format_date((int) $obj->$a, 'full');
        }

        // output field name
        $row[] = [
          'data' => $a,
        ];
/*        $row[] = [
          'data' => [
            '#title' => $this->t('IP address'),
            '#type' => 'textfield',
            '#size' => 48,
            '#maxlength' => 40,
            '#default_value' => '777',
            '#description' => $this->t('Enter a valid IP address.'),
          ],
        ];*/

        // determines how to output field
        if (!$b['editable']) {
            $tmpx = array(
                '#title' => $obj->$a,
                '#type' => 'item',
                '#description' => $fieldTypeDesc,
            );
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
            $row[] = [
              'data' => array(
                '#type' => 'item',
                '#markup' => kdevel_print_object($tmp),
                '#description' => $fieldTypeDesc,
              ),
            ];
        }
        else {
            $tmpx = array(
                '#type' => 'item',
                '#description' => $fieldTypeDesc,
                '#markup' => $obj->$a,
            );
/*            switch ($b['type'])    {
                case 'boolean':
                    $tmpx['#type'] = 'checkbox';
                    break;
                case 'integer':
                case 'time':
                    $tmpx['#type'] = 'number';
                    $tmpx['#size'] = 12;
                    $tmpx['#field_suffix'] = $suffixDesc;
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
            }*/
            $row[] = [
              'data' => $tmpx,
            ];
        }
      $rows[] = $row;
      $tabRow++;
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
