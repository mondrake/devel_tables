<?php

/**
 * @file
 * Contains \Drupal\devel_tables\Form\SettingsForm.
 */

namespace Drupal\devel_tables\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main devel_tables settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'devel_tables_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['devel_tables.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['list_tables'] = array('#type' => 'fieldset', '#title' => t('Table list settings'));
    $form['list_tables']['display_prefix'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display tables\' prefix'),
        '#default_value' => $this->config('devel_tables.settings')->get('list_tables.display_prefix'),
        '#description' => t('Displays the prefix of each of the Drupal tables in the current connection, if table prefixes were set at installation.'),
    );
    $form['list_tables']['display_row_count'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display tables\' total rows'),
        '#default_value' => $this->config('devel_tables.settings')->get('list_tables.display_row_count'),
        '#description' => t('Displays the current number of rows in each table. <b>Note:</b> Row count uses database statistics functions and not precise count, so it can be approximate.'),
    );
    $form['list_tables']['display_collation'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display tables\' collation'),
        '#default_value' => $this->config('devel_tables.settings')->get('list_tables.display_collation'),
        '#description' => t('Displays the collation of each table, as reported by the database system.'),
    );
    $form['list_tables']['display_storage_method'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display tables\' storage method'),
        '#default_value' => $this->config('devel_tables.settings')->get('list_tables.display_storage_method'),
        '#description' => t('Displays the storage method of each table, as reported by the database system.'),
    );

    $form['list_records'] = array('#type' => 'fieldset', '#title' => t('Record list settings'));
    $form['list_records']['kint_blob'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use Krumo for BLOB fields'),
        '#default_value' => $this->config('devel_tables.settings')->get('list_records.kint_blob'),
        '#description' => t('Use Krumo for BLOB fields.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) { }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('devel_tables.settings')
      ->set('list_tables.display_prefix', $form_state->getValue('display_prefix'))
      ->set('list_tables.display_row_count', $form_state->getValue('display_row_count'))
      ->set('list_tables.display_collation', $form_state->getValue('display_collation'))
      ->set('list_tables.display_storage_method', $form_state->getValue('display_storage_method'))
      ->set('list_records.kint_blob', $form_state->getValue('kint_blob'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
