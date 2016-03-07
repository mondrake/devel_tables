<?php

namespace Drupal\devel_tables\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for devel_tables driver plugins.
 */
class DevelTablesDriverPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/devel_tables', $namespaces, $module_handler, 'Drupal\devel_tables\Plugin\DevelTablesDriverInterface', 'Drupal\devel_tables\Plugin\Annotation\DevelTablesDriver');
    $this->alterInfo('devel_tables_driver_plugin_info');
    $this->setCacheBackend($cache_backend, 'devel_tables_driver_plugins');
  }

}
