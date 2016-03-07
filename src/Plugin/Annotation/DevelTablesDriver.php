<?php

namespace Drupal\devel_tables\Plugin\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Plugin annotation object for the devel_tables driver plugin.
 *
 * @Annotation
 */
class DevelTablesDriver extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the driver.
   *
   * The string should be wrapped in a @Translation().
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * A informative description of the driver.
   *
   * The string should be wrapped in a @Translation().
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $help;

}
